<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Validate_fields;
use App\Models\Fields_mapping;
use App\Models\Project;
use App\Exceptions\SyncAborted;
use App\Services\SyncConflictChecker;
use App\Services\SyncRunner;
use Illuminate\Support\Collection;

class ApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['ingest_validated', 'ingest_validated_nested']]);
    }

    public function ingest_validated_nested(Request $request, $id = null)
    {
        echo "<h2>Starting Ingestion Process</h2>";

        // A specific record id may be targeted from the /validated dashboard
        // (query string ?id= or route param). When set, that exact record is
        // processed regardless of its current status or of the internal id
        // order — the admin decides what gets synced. When absent, the default
        // behaviour applies: the oldest status=1 record that has a mapping.
        $targetId = $id ?? $request->query('id');

        // Resolve the target record set up front so an invalid targeted id fails
        // fast, before we bother authenticating against Specify.
        if ($targetId) {
            $records = Validate_fields::where('id', $targetId)->whereHas('field.mapping')->get();

            if ($records->isEmpty()) {
                echo "<hr><b style='color:red'>Record #" . htmlspecialchars($targetId)
                    . " was not found, or its field has no API mapping — nothing to sync.</b>";
                echo "<br><a href='" . url('/validated') . "'>&#8592; Back to validated list</a>";
                return;
            }
        } else {
            // status 6 = an admin approved overwriting Specify's different value; queued like a pending one
            $records = Validate_fields::whereIn('status', [1, 6])->whereHas('field.mapping')->limit(1)->orderBy('id')->get();
        }

        $dryRun = (bool) $request->query('dryrun');
        $failure = null;

        ob_start();
        try {
            $this->syncRecords($records, $targetId, $dryRun, true);
        } catch (SyncAborted $e) {
            $failure = $e->getMessage();
        }
        $page = ob_get_clean();
        echo $page;

        // a dry run changes nothing, so it has no place in the history
        if (! $dryRun) {
            app(SyncRunner::class)->recordManual($records, $page, auth()->id(), $failure);
        }
        if ($failure) {
            echo $failure;   // nothing more to show: the run could not start
        }
    }

    /**
     * Sends validated records to Specify, writing what it does as HTML (a person reads it on the manual page; the
     * scheduled sync captures it into the log of the run). The records are those of one project: the session is logged
     * in to that project's collection. A record that fails is marked as an error and the others go on.
     *
     * $manual adds the preview of the next record and the links that only make sense in a browser.
     *
     * @throws SyncAborted when nothing can be synced at all (no connection, no login)
     */
    public function syncRecords(Collection $records, $targetId = null, bool $dryRun = false, bool $manual = true): void
    {
        $session_id  = session()->getId();
        $cookie_file = specify_cookie_file();
        $specify7URL = config('services.specify.url');

        // --- AUTHENTICATION ---
        // The record being synced belongs to a project, and a project may sync into its own collection. The session
        // is logged in to one collection at a time, so log in again when the one wanted is not the one it is in.
        $wanted = (string) ($records->first()?->project_id ? Project::find($records->first()->project_id)?->specify_collection : null);
        $wanted = $wanted !== '' ? $wanted : (string) config('services.specify.collection');
        if ($wanted === '') {
            throw new SyncAborted('No collection to log in to: set the collection of the project, or API_COLLECTION in .env (Specify cannot be used without one).');
        }

        $API_auth_user = API_is_loggedin();
        if ($API_auth_user === false || (string) session('specify_collection', config('services.specify.collection')) !== $wanted) {
            echo "Login required. Authenticating...<br>";
            $API_auth_user = API_login($wanted !== '' ? $wanted : null);
        }

        if (!$API_auth_user) {
            throw new SyncAborted('Critical error: could not authenticate with the API.');
        }

        $collectionsByUri = array_column($API_auth_user['available_collections'], null, 'resource_uri');

        // --- CSRF EXTRACTION ---
        $cookie_content    = file_exists($cookie_file) ? file_get_contents($cookie_file) : '';
        $cookies_extracted = $this->extractCookies($cookie_content);

        $csrftoken = $this->csrfToken($cookies_extracted, $specify7URL);

        $headers = [
            "X-CSRFToken: $csrftoken",
            "Referer: $specify7URL",
            "Accept: application/json"
        ];

        // --- LOAD OPENAPI SCHEMA (once, for date field detection) ---
        $schemaRaw  = $this->executeCurl($specify7URL . '/api/specify_schema/openapi.json', null, $cookie_file, $headers);
        $schemaData = json_decode($schemaRaw, true);
        $apiSchema  = $schemaData['components']['schemas'] ?? [];

        // --- LOAD DATA MODEL (once, for relationship type detection) ---
        // $relInfo[<table>][<relation>] = ['type' => 'one-to-many'|'many-to-one'|'zero-to-one'|..., 'dependent' => bool]
        $datamodelRaw    = $this->executeCurl($specify7URL . '/context/datamodel.json', null, $cookie_file, $headers);
        $datamodelParsed = json_decode($datamodelRaw, true);
        $relInfo         = $this->buildRelInfo($datamodelParsed ?: []);

        if (empty($relInfo)) {
            // A failed/empty datamodel fetch makes normalizeDependents() a silent
            // no-op — dependent one-to-many relations (e.g. collectors) then go out
            // as a plain object instead of a list, and Specify rejects them with an
            // AssertionError that gives no hint this is the cause. Surface it loudly
            // instead of failing quietly record after record.
            echo "<p style='background:#f8d7da;border:1px solid #f5c2c7;padding:8px;'>"
                . "<b>WARNING:</b> could not load or parse <code>/context/datamodel.json</code> — "
                . "dependent to-many relations (e.g. collectors) will NOT be normalized into lists "
                . "for this run, and PUTs involving them will likely fail.</p>";
        }

        // --- PROCESSING ---
        if ($targetId) {
            echo "<p style='background:#fff3cd;border:1px solid #ffe69c;padding:8px;'>"
                . "<b>Manual sync</b> — forcing record #" . htmlspecialchars($targetId)
                . " (current status is ignored).</p>";
        }

        $record = null;
        foreach ($records as $record) {
            // (the body of the loop is not indented under this try: it is only a safety net around the whole record)
            try {
            $root = $this->resolveSyncRoot($record);
            if (!$root) {
                $errorMessage = 'Record #' . $record->id . ' has no resolvable project (project_id='
                    . ($record->project_id ?? 'null') . ') — cannot determine which Specify table/field to sync against.';
                echo "<hr><b style='color:red'>ERROR: " . htmlspecialchars($errorMessage) . "</b><br>";
                $record->status = 3;
                $record->error  = $errorMessage;
                $record->save();
                continue;
            }
            [$rootTable, $rootField, $rootValue] = $root;

            echo "<hr>";
            echo "<h3>Record ID: {$record->id} | " . htmlspecialchars($rootTable) . '.' . htmlspecialchars($rootField)
                . ": <code>" . htmlspecialchars($rootValue) . "</code></h3>";
            echo "Field: <b>{$record->field->name}</b> (field_id={$record->field_id})<br>";
            echo "Raw value: <code style='background:#eee;padding:2px 6px;'>" . htmlspecialchars($record->value) . "</code><br>";

            // A field's mapping must be rooted at the same table as the project
            // syncing it — that's the convention the whole configurable-root design
            // relies on (see [[configurable-sync-identifier]]), but nothing enforced
            // it until now. Mismatched, buildDelta() still "succeeds": it blindly
            // strips whatever the mapping's own root segment is (assuming it's
            // $rootTable) and recursiveMerge() then writes the leftover path as a
            // brand new, made-up top-level key on $exist — which Specify's PUT just
            // ignores. HTTP 200, "SUCCESS", nothing actually saved. Catch it here,
            // before wasting API calls (agent search/create, etc.) on a sync that
            // was never going to write anywhere real.
            $rootMismatchError = $this->checkMappingRootMatches($record, $rootTable);
            if ($rootMismatchError) {
                echo "<b style='color:red'>ERROR: " . htmlspecialchars($rootMismatchError) . "</b><br>";
                $record->status = 3;
                $record->error  = $rootMismatchError;
                $record->save();
                continue;
            }

            // Fetch the root entity from Specify (collectionobject/catalognumber
            // by default — every project before sync_root_table/sync_root_field
            // existed worked this way; a project can instead root its fields
            // at, say, preparation/text1, to sync at preparation granularity).
            $url_search   = $specify7URL . '/api/specify/' . $rootTable . '/?' . \App\Services\RootLookup::query($rootField, $rootValue);
            $ch           = curl_init($url_search);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response_raw = curl_exec($ch);
            curl_close($ch);

            $data  = json_decode($response_raw, true);
            [$exist, $ambiguous] = \App\Services\RootLookup::pick($rootField, $data['objects'] ?? [], Project::find($record->project_id)?->specify_collection);

            if (!$exist) {
                $errorMessage = $ambiguous ?? ucfirst($rootTable) . " with $rootField=$rootValue not found in Specify.";
                echo "<b style='color:red'>ERROR: $errorMessage</b><br>";
                $record->status = 3;
                $record->error  = $errorMessage;
                $record->save();
                continue;
            }

            echo "<b style='color:green'>OK: " . ucfirst($rootTable) . " found — {$exist['resource_uri']}</b><br>";
            $objectId      = $exist['id'];
            $specifyWebUrl = rtrim($specify7URL, '/') . '/specify/view/' . $rootTable . '/' . $objectId . '/';
            echo "<a href='$specifyWebUrl' target='_blank'>Open in Specify</a><br>";

            // Only CollectionObject carries a direct 'collection' FK — every other
            // table (Preparation, ...) is scoped to a collection via the numeric
            // 'collectionmemberid' instead, with no 'collection' key at all.
            //
            // $collectionUri/$discipline are only ever consumed later when a related
            // resource actually needs creating (findOrCreateResource()'s create path)
            // — a field mapped as a plain direct write (e.g. Preparation.samplenumber,
            // no pick/node involved) never touches them at all. So a table that
            // exposes neither field is left with $discipline = null here rather than
            // failing the whole record outright; the sync only breaks later, and only
            // if something concrete actually needed discipline and didn't have it.
            if (isset($exist['collection'])) {
                $collectionUri = $exist['collection'];
            } elseif (isset($exist['collectionmemberid'])) {
                $collectionUri = '/api/specify/collection/' . $exist['collectionmemberid'] . '/';
            } else {
                $collectionUri = null;
                echo "<span style='color:#e67e00'>Note: " . ucfirst($rootTable)
                    . " record has neither a 'collection' nor a 'collectionmemberid' field — "
                    . "discipline/collection can't be resolved. Fine unless this record needs to create a related resource.</span><br>";
            }
            $discipline = $collectionUri ? ($collectionsByUri[$collectionUri]['discipline'] ?? null) : null;
            if ($discipline === null && $collectionUri) {
                // the list is the one of the collection the session is logged in to: a record of another collection is not in it
                $collectionData = json_decode($this->executeCurl($specify7URL . $collectionUri, null, $cookie_file, $headers), true);
                $discipline = $collectionData['discipline'] ?? null;
            }

            // Load required-field defaults configured by the admin for this field.
            // Format: ["table.fieldname" => "default value", ...]
            $requiredDefaults = $record->field->required_defaults ?? [];

            // ── n.a. SUBSTITUTION ───────────────────────────────────────────────
            // If the validated value is exactly "n.a.", replace it with the
            // admin-configured na_defaults before splitting into parts.
            // This preserves "n.a." in the transcription/validation layer while
            // allowing meaningful values to be ingested into Specify.
            //
            // na_defaults uses the same format as the field value (same explode_char).
            // Example: "n.a.||Denmark|||||Denmark|Denmark"
            // Parts that are empty strings are treated as "no value for this part"
            // and will be skipped during ingestion like any other empty part.
            //
            // If na_defaults is not configured, the record is skipped entirely
            // — ingesting a literal "n.a." into Specify is never useful.
            if (trim($record->value) === 'n.a.') {
                $naDefaults = $record->field->na_defaults ?? null;
                if ($naDefaults) {
                    echo "<b style='color:#e67e00'>n.a. value — substituting with configured default:</b> "
                        . "<code>" . htmlspecialchars($naDefaults) . "</code><br>";
                    $record->value = $naDefaults;
                } else {
                    echo "<b style='color:#999'>n.a. value — no default configured, skipping.</b><br>";
                    $record->status = 2;
                    $record->error  = 'Skipped: value is n.a. and no ingestion default is configured for this field.';
                    $record->save();
                    continue;
                }
            }
            // ── END n.a. SUBSTITUTION ───────────────────────────────────────────

            // STEP 1: Split value into parts.
            // If concatenate_parts is enabled, the value is kept whole and treated
            // as a single part at index 0 — matching how it is stored in fields_mapping.
            if ($record->field->concatenate_parts) {
                $value_parts = [0 => $record->value];
                echo "<p><b>Concatenated mode</b> — entire value mapped as single field: "
                    . "<code>" . htmlspecialchars($record->value) . "</code></p>";
            } else {
                $value_parts = $record->field->explode_char != ''
                    ? explode($record->field->explode_char, $record->value)
                    : [$record->value];
            }


            echo "<h4>Parts detected (" . count($value_parts) . "):</h4>";
            echo "<table border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse;font-size:13px;'>";
            echo "<tr style='background:#ddd'><th>Part</th><th>Value</th><th>Mapping</th></tr>";

            foreach ($value_parts as $partIndex => $valuePart) {
                $partMappings = $record->field->mapping->where('part', (string)$partIndex);
                $mappingLines = [];
                foreach ($partMappings as $m) {
                    $mappingLines[] = "[{$m->id}] {$m->api_table_field} ({$m->type})"
                        . ($m->rank_id ? " rank:{$m->rank_id}" : '')
                        . ($m->parent  ? " parent:{$m->parent}" : '');
                }
                echo "<tr><td style='text-align:center'><b>$partIndex</b></td>"
                    . "<td><code>" . htmlspecialchars($valuePart) . "</code></td>"
                    . "<td style='font-size:12px'>" . implode('<br>', $mappingLines) . "</td></tr>";
            }
            echo "</table><br>";

            $pickGroups = [];
            $writeNodes = [];

            foreach ($value_parts as $partIndex => $valuePart) {
                $rootMappings = $record->field->mapping
                    ->whereNull('parent')
                    ->where('part', $partIndex);

                foreach ($rootMappings as $rootMapping) {
                    $this->collectPicksAndWrites(
                        $rootMapping,
                        $record,
                        $partIndex,
                        $valuePart,
                        $pickGroups,
                        $writeNodes
                    );
                }
            }

            // STEP 1b: Register parent-child dependencies between pick groups
            foreach ($pickGroups as $sig => $group) {
                $sigParts     = explode('|', $sig);
                $pathSegments = explode('.', rtrim($sigParts[0], '.'));
                $parentTable  = array_pop($pathSegments);
                $parentPrefix = implode('.', $pathSegments);

                if (!$parentTable) continue;

                $parentSig = $parentPrefix . '|' . $parentTable . '|';
                if (isset($pickGroups[$parentSig])) {
                    $pickGroups[$parentSig]['child_mapping_ids'][$group['relation_field']] = $group['mapping_id'];
                }
            }

            // STEP 2: Sort by depth — deepest first
            uasort($pickGroups, fn($a, $b) => $b['depth'] <=> $a['depth']);

            // STEP 3: Identify reverse FK groups
            // Reverse FK: the relation_field name differs from the table name
            //   e.g. parent refers to 'geocoorddetails' but the table is 'geocoorddetail'
            // Forward FK: relation_field === table name
            //   e.g. parent refers to 'geography' and the table is 'geography'
            $reverseFkGroups = [];
            $skippedGroups   = [];   // groups with no value for this specimen — not searched, not created

            foreach ($pickGroups as $sig => $group) {
                $sigParts     = explode('|', $sig);
                $pathSegments = explode('.', rtrim($sigParts[0], '.'));
                $parentTable  = end($pathSegments);

                if (!$parentTable) continue;

                $parentSig = implode('.', array_slice($pathSegments, 0, -1)) . '|' . $parentTable . '|';
                if (!isset($pickGroups[$parentSig])) continue;

                if ($group['relation_field'] !== $group['table']) {
                    $reverseFkGroups[$sig] = true;
                }
            }

            echo "<h4>Pick groups (sorted deepest first):</h4>";
            echo "<table border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse;font-size:13px;'>";
            echo "<tr style='background:#ddd'><th>Sig</th><th>Table</th><th>Depth</th><th>Fields</th><th>FK type</th></tr>";
            foreach ($pickGroups as $sig => $g) {
                $fkType = isset($reverseFkGroups[$sig])
                    ? "<span style='color:orange'>reverse</span>"
                    : "<span style='color:blue'>forward</span>";
                $fields = implode(', ', array_map(
                    fn($k, $v) => "$k=<i>" . htmlspecialchars($v) . "</i>",
                    array_keys($g['fields']),
                    $g['fields']
                ));
                echo "<tr>"
                    . "<td style='font-size:11px'>" . htmlspecialchars($sig) . "</td>"
                    . "<td><b>{$g['table']}</b></td>"
                    . "<td>{$g['depth']}</td>"
                    . "<td>$fields</td>"
                    . "<td>$fkType</td>"
                    . "</tr>";
            }
            echo "</table><br>";

            $resolved = [];

            echo "<h4>STEP 3 — forward FK resolution:</h4>";
            foreach ($pickGroups as $sig => &$group) {
                $searchFields = $group['fields'];

                // Inject any already-resolved child URIs into the search fields
                foreach ($group['child_mapping_ids'] as $childField => $childMappingId) {
                    if (isset($resolved[$childMappingId])) {
                        $searchFields[$childField] = $resolved[$childMappingId];
                    }
                }

                // Nothing to search or create by (no own values, no resolved children):
                // this relation branch has no data for this specimen — skip it entirely
                // instead of failing the whole record (STEP 3b honours $skippedGroups too).
                $hasData = false;
                foreach ($searchFields as $v) {
                    if ($v !== null && $v !== '') { $hasData = true; break; }
                }
                if (!$hasData) {
                    echo "  [skip] {$group['table']}: no value for this specimen.<br>";
                    $skippedGroups[$sig] = true;
                    continue;
                }

                if (isset($reverseFkGroups[$sig])) {
                    // Reverse FK: search only — parent URI not yet available.
                    // Creation happens in STEP 3b once the parent is resolved.
                    echo "  [reverse FK — deferred] {$group['table']}: searching...<br>";
                    $uri = $this->searchResource(
                        $group['table'],
                        $searchFields,
                        $headers,
                        $cookie_file,
                        $group['rank_id'] ?? null,
                        $discipline
                    );
                    if ($uri) {
                        echo "  <span style='color:green'>Found existing: $uri</span><br>";
                    } else {
                        echo "  <span style='color:#999'>Not found — will attempt create in STEP 3b.</span><br>";
                    }
                } else {
                    echo "  [forward FK] {$group['table']}: resolving...<br>";
                    $uri = $this->findOrCreateResource(
                        $group['table'],
                        $searchFields,
                        $headers,
                        $cookie_file,
                        $discipline,
                        $collectionUri,
                        $group['rank_id'] ?? null,
                        $requiredDefaults,
                        $dryRun
                    );
                }

                if ($uri) {
                    $resolved[$group['mapping_id']] = $uri;
                    echo "<span style='color:green'>  ✓ {$group['table']} → $uri</span><br>";
                } elseif (!isset($reverseFkGroups[$sig])) {
                    // Only treat missing URI as fatal for forward FK groups.
                    // Reverse FK groups that are not found here will be retried in STEP 3b.
                    $errorMessage = "Could not resolve or create resource for table '{$group['table']}' with fields: " . json_encode($searchFields);
                    echo "<span style='color:red'>  ✗ $errorMessage</span><br>";
                    $record->status = 3;
                    $record->error  = $errorMessage;
                    $record->save();
                    continue 2;
                }
            }
            unset($group);

            // STEP 3b: Create reverse FK children now that their parent URI is resolved.
            // The parent URI is injected as the child's FK field before create.
            echo "<h4>STEP 3b — reverse FK children:</h4>";
            foreach ($pickGroups as $sig => $group) {
                if (!isset($reverseFkGroups[$sig])) continue;
                if (isset($skippedGroups[$sig])) continue;   // no value for this specimen

                $sigParts     = explode('|', $sig);
                $pathSegments = explode('.', rtrim($sigParts[0], '.'));
                $parentTable  = array_pop($pathSegments);
                $parentPrefix = implode('.', $pathSegments);
                $parentSig    = $parentPrefix . '|' . $parentTable . '|';

                if (!isset($pickGroups[$parentSig])) {
                    echo "  <span style='color:red'>✗ {$group['table']}: parent group not found (sig: $parentSig)</span><br>";
                    continue;
                }

                $parentGroup = $pickGroups[$parentSig];
                if (!isset($resolved[$parentGroup['mapping_id']])) {
                    echo "  <span style='color:red'>✗ {$group['table']}: parent URI not resolved (mapping_id: {$parentGroup['mapping_id']})</span><br>";
                    continue;
                }

                $parentUri                 = $resolved[$parentGroup['mapping_id']];
                $childFields               = $group['fields'];
                $childFields[$parentTable] = $parentUri;

                echo "  {$group['table']} with {$parentTable}={$parentUri}...<br>";
                echo "  Fields: " . htmlspecialchars(json_encode($childFields)) . "<br>";

                // Check applicable defaults for this table
                $tableDefaults = array_filter($requiredDefaults, function ($key) use ($group) {
                    return strtolower(explode('.', $key)[0] ?? '') === strtolower($group['table']);
                }, ARRAY_FILTER_USE_KEY);
                if (!empty($tableDefaults)) {
                    echo "  Applying defaults: " . htmlspecialchars(json_encode($tableDefaults)) . "<br>";
                }

                $uri = $this->findOrCreateResource(
                    $group['table'],
                    $childFields,
                    $headers,
                    $cookie_file,
                    $discipline,
                    $collectionUri,
                    null,
                    $requiredDefaults,
                    $dryRun
                );

                if ($uri) {
                    $resolved[$group['mapping_id']] = $uri;
                    echo "  <span style='color:green'>✓ {$group['table']} → $uri</span><br>";
                } else {
                    $errorMessage = "Could not resolve or create reverse FK resource for table '{$group['table']}' with fields: " . json_encode($childFields);
                    echo "  <span style='color:red'>✗ $errorMessage</span><br>";
                    $record->status = 3;
                    $record->error  = $errorMessage;
                    $record->save();
                    continue 2;
                }
            }

            // STEP 4: Build the delta — shallowest resolved URIs + direct write values
            $position = (int)($record->position ?? 0);
            $delta    = $this->buildDelta($writeNodes, $resolved, $pickGroups);

            if (empty($delta)) {
                $errorMessage = 'Delta is empty — no mappings could be resolved for this record.';
                echo "<b style='color:red'>ERROR: $errorMessage</b><br>";
                $record->status = 3;
                $record->error  = $errorMessage;
                $record->save();
                continue;
            }

            // a name that is neither a field nor a relation of the table is ignored by Specify, which still answers 200:
            // the record would be marked as synced with nothing saved. The data model tells, so refuse it here.
            $unknown = $this->unknownDeltaKey($delta, $relInfo, $rootTable);
            if ($unknown) {
                $errorMessage = "Field '{$record->field->name}' is mapped to '{$unknown}', which does not exist in this Specify (a mapping error): nothing was written.";
                echo "<b style='color:red'>ERROR: " . htmlspecialchars($errorMessage) . "</b><br>";
                $record->status = 3;
                $record->error  = $errorMessage;
                $record->save();
                continue;
            }

            // STEP 4a: wrap dependent to-many relations (collectors, localitynamealiass, ...) as lists
            $this->normalizeDependents($delta, $relInfo, $rootTable, $position);

            echo "<h4>Delta to merge:</h4><pre style='background:#f4f4f4;border:1px solid #ccc;padding:8px;'>"
                . htmlspecialchars(json_encode($delta, JSON_PRETTY_PRINT)) . "</pre>";

            // STEP 4b: Apply date precision — complete partial dates and inject *precision fields
            $delta = $this->applyDatePrecision($delta, $apiSchema, $rootTable, $relInfo);

            // STEP 4c: when the project must not overwrite, drop every part whose
            // value (or pick link) differs from what Specify already holds.
            // Empty or equal parts still go through; the rest is a conflict.
            $conflicts = [];
            $project   = $record->project_id ? Project::find($record->project_id) : null;
            if ($this->shouldCheckForConflicts($record, $project)) {
                $checker = SyncConflictChecker::forProject($project, function (string $uri) use ($specify7URL, $cookie_file, $headers) {
                    return json_decode($this->executeCurl($specify7URL . $uri, null, $cookie_file, $headers), true);
                });
                $check     = $checker->check($delta, $exist, $position);
                $delta     = $check['delta'];
                $conflicts = $check['conflicts'];

                echo "<h4>Existing values in Specify (project does not overwrite):</h4>";
                echo "<table border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse;font-size:13px;'>";
                echo "<tr style='background:#ddd'><th>Part</th><th>In Specify</th><th>Ours</th><th>Decision</th></tr>";
                foreach ($check['decisions'] as $d) {
                    $color = $d['decision'] === 'conflict' ? 'red' : ($d['decision'] === 'equal' ? '#999' : 'green');
                    echo "<tr><td>" . htmlspecialchars($d['path']) . "</td>"
                        . "<td><code>" . htmlspecialchars((string) $d['existing']) . "</code></td>"
                        . "<td><code>" . htmlspecialchars((string) $d['proposed']) . "</code></td>"
                        . "<td style='color:$color'><b>" . $d['decision'] . "</b></td></tr>";
                }
                echo "</table><br>";

                if ($conflicts && empty($delta) && !$dryRun) {
                    $this->markConflict($record, $conflicts);
                    echo "<b style='color:#e67e00'>CONFLICT: every part already has a different value in Specify — nothing written.</b><br>";
                    continue;
                }
            }

            // STEP 5: Merge delta into the existing CollectionObject and submit via PUT
            $this->expandLinkedObjects($delta, $exist, $specify7URL, $cookie_file, $headers);
            $this->newObjectDefaults = $requiredDefaults;
            $this->schemaTables      = $apiSchema;

            // Specify gives every object it creates in a table scoped to a collection (determinations, preparations, other
            // identifiers...) the collection the session is logged in to, whatever the payload says. So a record of another
            // collection gets them in the wrong one: say it, the project must use the collection of its records.
            if ($collectionUri && preg_match('#/(\d+)/$#', $collectionUri, $cm) && $cm[1] !== $wanted) {
                echo "<span style='color:#e67e00'>Note: this record belongs to collection {$cm[1]} but the session is logged in to collection {$wanted}: objects Specify creates in tables scoped to a collection (determinations, preparations, other identifiers...) are given collection {$wanted}. Set the collection of the project to {$cm[1]}.</span><br>";
            }
            $this->addDisciplineToNewObjects($delta, $exist, $relInfo, $rootTable, $discipline, $position);
            $this->recursiveMerge($exist, $delta, $position);

            $url_put = $specify7URL . $exist['resource_uri'];
            unset($exist['timestampcreated'], $exist['timestampmodified']);

            $payload = json_encode($exist);

            if ($dryRun) {
                echo "<h4 style='color:#b30000'>DRY RUN — PUT skipped.</h4>";
                echo "<h4>Merged payload that would be PUT:</h4><pre style='background:#eef;border:1px solid #ccc;padding:8px;'>"
                    . htmlspecialchars(json_encode($exist, JSON_PRETTY_PRINT)) . "</pre>";
                continue;
            }

            $ch = curl_init($url_put);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $put_output = curl_exec($ch);
            $put_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo "<strong>PUT response: HTTP $put_code</strong><br>";
            if ($put_code >= 200 && $put_code < 300) {
                if ($conflicts) {
                    echo "<b style='color:#e67e00'>PARTIAL: record updated, but " . count($conflicts)
                        . " part(s) already had a different value in Specify and were left as they were.</b><br>";
                    $this->markConflict($record, $conflicts);
                } else {
                    echo "<b style='color:green'>SUCCESS: Record updated.</b><br>";
                    $record->status = 2;
                    $record->error  = null;
                    $record->save();
                }
            } else {
                $errorMessage = $put_output;
                $errorData    = json_decode($put_output, true);
                if (!empty($errorData['message'])) {
                    $errorMessage = $errorData['message'];
                    // a database error comes as a code ("1048") and the explanation in 'data': keep both
                    if (!empty($errorData['data']) && is_string($errorData['data'])) {
                        $errorMessage = trim(($errorData['exception'] ?? 'Error') . ': ' . $errorData['data'] . ' (' . $errorMessage . ')');
                    }
                }
                echo "<b style='color:red'>PUT ERROR:</b><pre>" . htmlspecialchars($put_output) . "</pre>";
                $record->status = 3;
                $record->error  = $errorMessage;
                $record->save();
            }
            } catch (\Throwable $e) {
                // one record failing must not stop the others, nor stay first in the queue to fail again on every run
                echo "<b style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</b><br>";
                $record->status = 3;
                $record->error  = mb_substr($e->getMessage(), 0, 1000);
                $record->save();
            }
        }

        if (!$manual) {
            return;
        }

        // NEXT RECORD PREVIEW
        $next = Validate_fields::whereIn('status', [1, 6])->whereHas('field.mapping')->where('id', '>', $record->id ?? 0)->orderBy('id')->first();

        $nextRoot = $next ? $this->resolveSyncRoot($next) : null;

        if ($next && $nextRoot) {
            [$nextTable, $nextField, $nextValue] = $nextRoot;
            $nextSearch     = $specify7URL . '/api/specify/' . $nextTable . '/?' . \App\Services\RootLookup::query($nextField, $nextValue);
            $ch             = curl_init($nextSearch);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $nextRaw        = curl_exec($ch);
            curl_close($ch);

            $nextData       = json_decode($nextRaw, true);
            $nextObjectId   = \App\Services\RootLookup::pick($nextField, $nextData['objects'] ?? [], Project::find($next->project_id)?->specify_collection)[0]['id'] ?? null;
            $nextSpecifyUrl = $nextObjectId
                ? rtrim($specify7URL, '/') . '/specify/view/' . $nextTable . '/' . $nextObjectId . '/'
                : null;

            echo "<hr><h3>Next to process</h3>";
            echo "Record ID: <b>{$next->id}</b> | " . htmlspecialchars($nextTable) . '.' . htmlspecialchars($nextField)
                . ": <code>" . htmlspecialchars($nextValue) . "</code><br>";
            echo "Field: <b>{$next->field->name}</b> (field_id={$next->field_id})<br>";
            echo "Value: <code style='background:#eee;padding:2px 6px;'>" . htmlspecialchars($next->value) . "</code><br>";
            if ($nextSpecifyUrl) {
                echo "<a href='$nextSpecifyUrl' target='_blank'>Open in Specify</a><br><br>";
            }
            $nextUrl = url('/validate/manual/' . $next->id);
            echo "<a href='$nextUrl' style='display:inline-block;padding:10px 24px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;'>&#9654; Process next</a>";
        } elseif ($next) {
            // there IS a next record, just its project couldn't be resolved for
            // this preview — still offer to process it (it'll surface its own
            // proper error there) rather than falsely saying nothing is left.
            $nextUrl = url('/validate/manual/' . $next->id);
            echo "<hr><h3>Next to process</h3>";
            echo "Record ID: <b>{$next->id}</b> — project could not be resolved for preview.<br>";
            echo "<a href='$nextUrl' style='display:inline-block;padding:10px 24px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;'>&#9654; Process next</a>";
        } else {
            echo "<hr><b style='color:green'>No more records to process.</b>";
        }

        echo "<br><br><a href='" . url('/validated') . "'>&#8592; Back to validated list</a>";
    }

    // -------------------------------------------------------------------------
    // SUPPORT METHODS
    // -------------------------------------------------------------------------

    /**
     * Only a project set to 'keep_existing' ever checks Specify's current values —
     * the default 'overwrite' (every project before this setting existed) syncs
     * exactly as it always did. Status 6 (an admin approved overwriting) skips the
     * check too: re-checking would just flag the same conflict again.
     */
    private function shouldCheckForConflicts(Validate_fields $record, ?Project $project): bool
    {
        return $project !== null
            && $project->sync_overwrite === 'keep_existing'
            && (int) $record->status !== 6;
    }

    /** Status 4 = conflict: Specify already holds a different value for at least one part. */
    private function markConflict(Validate_fields $record, array $conflicts): void
    {
        // Only the status and a one-line summary are kept; the admin screen reads what Specify
        // holds live, so nothing from Specify is stored here that could go stale.
        $record->status   = 4;
        $record->error    = 'Different value already in Specify: ' . implode(', ', array_column($conflicts, 'path'));
        $record->save();
    }

    /**
     * Resolves what to search Specify for to find a validated record's root
     * entity: the project's sync_root_table/sync_root_field (default
     * collectionobject/catalognumber — how this pipeline always worked
     * before those became configurable) and the value to match it against,
     * derived from the record's own specimen_id.
     *
     * Catalog numbers are conventionally zero-padded to 9 digits in this
     * Specify installation; every other root field is matched on the raw
     * specimen_id value, since it isn't necessarily numeric or fixed-width
     * (e.g. a project rooted at preparation/text1 free-form).
     *
     * Pure lookup, no side effects — returns null if the record's project
     * can't be resolved and leaves it to the caller to decide what that
     * means (a hard failure mid-sync vs. just skipping the "next record"
     * preview).
     */
    private function resolveSyncRoot(Validate_fields $record): ?array
    {
        $project = $record->project_id ? Project::find($record->project_id) : null;

        if (!$project) {
            return null;
        }

        $table = strtolower($project->sync_root_table ?: 'collectionobject');
        $field = strtolower($project->sync_root_field ?: 'catalognumber');
        $value = \App\Services\RootLookup::value($record->specimen_id);

        return [$table, $field, $value];
    }

    /**
     * A field's mapping must be rooted at the same table as the project
     * syncing it — the convention this whole configurable-root design relies
     * on (a field wanting a different root needs its own separately-mapped
     * field, same as botany/entomology already have separate locality
     * fields), but nothing enforced it before this check existed.
     *
     * Mismatched, the sync doesn't fail loudly: buildDelta() unconditionally
     * strips the mapping's own root segment (assuming it's always
     * $rootTable), and recursiveMerge() then writes whatever's left as a
     * brand new, made-up top-level key on the fetched root entity — which
     * Specify's PUT silently ignores. The sync reports HTTP 200 / "SUCCESS"
     * having written nothing at all.
     *
     * Returns an error message if any of the field's root mapping nodes (one
     * per value part) declare a different table than $rootTable, null if
     * they all agree (or the field has no mapping at all — a separate,
     * already-handled failure).
     */
    private function checkMappingRootMatches(Validate_fields $record, string $rootTable): ?string
    {
        $mismatchedRoots = [];
        foreach ($record->field->mapping->whereNull('parent') as $rootMappingNode) {
            $mappingRootTable = strtolower(explode('.', $rootMappingNode->api_table_field)[0] ?? '');
            if ($mappingRootTable !== '' && $mappingRootTable !== $rootTable) {
                $mismatchedRoots[$mappingRootTable] = true;
            }
        }

        if (empty($mismatchedRoots)) {
            return null;
        }

        return "Field '{$record->field->name}' is mapped rooted at '" . implode("', '", array_keys($mismatchedRoots))
            . "', but this project syncs at root '{$rootTable}' — configure a separate mapping for this field rooted at '{$rootTable}', or fix the project's sync_root_table.";
    }

    /**
     * Builds a relationship lookup from Specify's /context/datamodel.json:
     *
     *   $relInfo['collectingevent']['collectors'] = [
     *       'type'      => 'one-to-many',   // one-to-many | many-to-one | zero-to-one | one-to-one | many-to-many
     *       'dependent' => true,            // Specify inlines dependent relations, links independent ones by URI
     *       'related'   => 'collector',
     *   ]
     *
     * Keys are lower-cased so lookups are case-insensitive against the mapping table names.
     */
    private function buildRelInfo(array $datamodel): array
    {
        $relInfo = [];
        foreach ($datamodel as $tableDef) {
            $table = strtolower($tableDef['table'] ?? '');
            if ($table === '') continue;

            foreach ($tableDef['relationships'] ?? [] as $rel) {
                $name = strtolower($rel['name'] ?? '');
                if ($name === '') continue;

                $relInfo[$table][$name] = [
                    'type'      => strtolower($rel['type'] ?? 'many-to-one'),
                    'dependent' => (bool) ($rel['dependent'] ?? false),
                    'related'   => strtolower($rel['relatedModelName'] ?? ''),
                ];
            }

            // the lines of a list are told apart by their ordernumber only in the tables that have one (collectors)
            foreach ($tableDef['fields'] ?? [] as $field) {
                $fieldName = strtolower($field['name'] ?? '');
                $relInfo[$table]['#fields'][$fieldName] = true;
                if ($fieldName === 'ordernumber') {
                    $relInfo[$table]['#ordered'] = true;
                }
            }
        }
        return $relInfo;
    }

    /**
     * Recursively traverses the mapping tree and collects:
     * - pick groups keyed by path signature (unique per hierarchy path + table)
     * - write nodes with the value to write directly
     */
    private function collectPicksAndWrites(
        $mapping,
        $record,
        int $partIndex,
        string $valuePart,
        array &$pickGroups,
        array &$writeNodes,
        int $depth = 0,
        ?int $parentPickMappingId = null,
        string $pathPrefix = '',
        string $relationField = ''
    ) {
        $parts = explode('.', $mapping->api_table_field);
        if (count($parts) < 2) return;

        [$tableRaw, $fieldRaw] = $parts;
        $table = strtolower($tableRaw);
        $field = strtolower($fieldRaw);

        if ($mapping->type === 'write') {
            // the path is made of the names of the relations (collectionobject.determinations.remarks): the name of the
            // table the leaf belongs to (determination) is the same only for a relation to one object (collectingevent)
            $writeNodes[] = [
                'path'  => implode('.', $this->getMappingPath($mapping)),
                'value' => $valuePart,
            ];
            return;
        }

        if ($mapping->type === 'pick') {
            $sig = $pathPrefix . '|' . $table . '|' . ($mapping->rank_id ?? '');

            if (!isset($pickGroups[$sig])) {
                $pickGroups[$sig] = [
                    'table'             => $table,
                    'relation_field'    => $relationField,
                    'depth'             => $depth,
                    'fields'            => [],
                    'rank_id'           => $mapping->rank_id,
                    'mapping_id'        => $mapping->id,
                    'child_mapping_ids' => [],
                ];
            }

            $pickGroups[$sig]['fields'][$field] = $valuePart;
            return;
        }

        if ($mapping->type === 'node') {
            $children = $record->field->mapping
                ->where('parent', $mapping->id)
                ->where('part', $partIndex);

            $currentSig           = $pathPrefix . '|' . $table . '|';
            $currentPickMappingId = isset($pickGroups[$currentSig])
                ? $pickGroups[$currentSig]['mapping_id']
                : $parentPickMappingId;

            foreach ($children as $child) {
                $this->collectPicksAndWrites(
                    $child,
                    $record,
                    $partIndex,
                    $valuePart,
                    $pickGroups,
                    $writeNodes,
                    $depth + 1,
                    $currentPickMappingId,
                    $pathPrefix . '.' . $table,
                    $field
                );
            }
        }
    }

    /**
     * Builds the delta array for recursiveMerge.
     * Only inserts the shallowest resolved URI per chain — children are implicit.
     */
    private function buildDelta(array $writeNodes, array $resolved, array $pickGroups): array
    {
        $delta = [];

        // 1. Direct write values
        // Path format: 'collectionobject.table.field' — shift removes the root table
        foreach ($writeNodes as $wn) {
            $keys = array_values(array_filter(explode('.', ltrim($wn['path'], '.'))));
            array_shift($keys); // drop the root segment — whatever table it names (collectionobject,
            // preparation, ...), it's the record already fetched as $exist, not part of the delta

            if (empty($keys)) continue;

            $ref = &$delta;
            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    $ref[$k] = $wn['value'];
                } else {
                    if (!isset($ref[$k]) || !is_array($ref[$k])) $ref[$k] = [];
                    $ref = &$ref[$k];
                }
            }
            unset($ref);
        }

        // 2. Resolved URIs — only the shallowest (non-child) URI per chain
        $childMappingIds = [];
        foreach ($pickGroups as $group) {
            foreach ($group['child_mapping_ids'] as $childMappingId) {
                $childMappingIds[] = $childMappingId;
            }
        }

        foreach ($resolved as $mappingId => $uri) {
            if (in_array($mappingId, $childMappingIds)) continue;

            $mapping = Fields_mapping::find($mappingId);
            if (!$mapping) continue;

            $path = $this->getMappingPath($mapping);
            echo "getMappingPath for mappingId $mappingId: " . implode(' → ', $path) . "<br>";
            array_pop($path);   // remove the pick field (e.g. 'localityname')
            array_shift($path); // drop the root segment — same reasoning as buildDelta() above
            echo "After pop+shift: " . implode(' → ', $path) . "<br>";

            if (empty($path)) continue;

            $ref = &$delta;
            foreach ($path as $i => $segment) {
                if ($i === count($path) - 1) {
                    $ref[$segment] = $uri;
                } else {
                    if (!isset($ref[$segment]) || !is_array($ref[$segment])) $ref[$segment] = [];
                    $ref = &$ref[$segment];
                }
            }
            unset($ref);
        }

        return $delta;
    }

    /**
     * Walks the delta and, for every relation Specify marks as a dependent
     * to-many, wraps its inline object in a single-element list carrying an
     * ordernumber. Dependent to-one relations stay as plain objects.
     *
     * This only touches relations that already ended up nested in the delta
     * (i.e. under an embedded parent such as collectingevent.collectors) — it
     * never turns a URI link into an object.
     */
    private function normalizeDependents(array &$delta, array $relInfo, string $table, int $position): void
    {
        foreach ($delta as $key => &$value) {
            if (!is_array($value)) continue;

            $rel        = $relInfo[strtolower($table)][strtolower($key)] ?? null;
            $childTable = ($rel && $rel['related'] !== '') ? $rel['related'] : strtolower($key);

            $this->normalizeDependents($value, $relInfo, $childTable, $position);

            if ($rel && $rel['dependent'] && $rel['type'] === 'one-to-many' && !$this->isNumericArray($value)) {
                if (!array_key_exists('ordernumber', $value) && !empty($relInfo[$childTable]['#ordered'])) {
                    $value['ordernumber'] = $position;
                }
                $value = [$value];
            }
        }
    }

    /**
     * The record Specify returns links an independent related object by its address ("collectingevent":
     * "/api/specify/collectingevent/79890/"); only a dependent one comes inlined. A value written inside such a link
     * (collectingevent.startdate) cannot be merged into a string: it would replace the link by a brand new object, which
     * Specify then CREATES (a duplicate, and one it refuses without a discipline), leaving the one that is there alone.
     * So the linked object is fetched and put in the place of its address: the merge then changes the object that is
     * there and the PUT updates it, with its address and version. Only the relations the delta writes into are fetched.
     */
    private function expandLinkedObjects(array $delta, array &$existing, string $specify7URL, string $cookie_file, array $headers): void
    {
        foreach ($delta as $key => $value) {
            if (!is_array($value) || $this->isNumericArray($value)) continue;

            $current = $existing[$key] ?? null;
            if (is_string($current) && str_starts_with($current, '/api/specify/')) {
                $fetched = json_decode($this->executeCurl($specify7URL . $current, null, $cookie_file, $headers), true);
                if (is_array($fetched) && !empty($fetched['resource_uri'])) {
                    $existing[$key] = $fetched;
                }
            }

            if (is_array($existing[$key] ?? null)) {
                $this->expandLinkedObjects($value, $existing[$key], $specify7URL, $cookie_file, $headers);
            }
        }
    }

    /**
     * An object that does not exist yet and is sent nested inside the record (a collecting event the record does not
     * have yet, a locality...) is created by Specify from exactly what it receives, and a table scoped to a discipline
     * refuses a record without one ("Column 'DisciplineID' cannot be null"). So every such new object whose table has a
     * discipline gets the discipline of the record's collection. Objects that already exist are left as they are.
     * The data model ($relInfo) says which tables have a discipline; without it nothing is added.
     */
    private function addDisciplineToNewObjects(array &$delta, array $existing, array $relInfo, string $table, ?string $discipline, int $position = 1): void
    {
        if (!$discipline) {
            return;
        }

        foreach ($delta as $key => &$value) {
            if (!is_array($value)) continue;

            $rel        = $relInfo[strtolower($table)][strtolower($key)] ?? null;
            $childTable = ($rel && $rel['related'] !== '') ? $rel['related'] : strtolower($key);
            $current    = $existing[$key] ?? null;

            if ($this->isNumericArray($value)) {
                // a list: each item is new unless the record already has one with the same ordernumber
                foreach ($value as &$item) {
                    if (!is_array($item)) continue;
                    $line  = is_array($current) ? \App\Services\ListLine::find($current, $item, $position) : null;
                    $match = $line !== null ? $current[$line] : null;
                    $this->scopeNested($item, $match, $relInfo, $childTable, $discipline, $position);
                }
                unset($item);
                continue;
            }

            $exists = is_array($current) || (is_string($current) && $current !== '');
            $this->scopeNested($value, $exists ? (is_array($current) ? $current : []) : null, $relInfo, $childTable, $discipline, $position);
        }
        unset($value);
    }

    /** $current is null when the object is new. */
    private function scopeNested(array &$object, ?array $current, array $relInfo, string $table, string $discipline, int $position = 1): void
    {
        if ($current === null && isset($relInfo[$table]['discipline']) && !array_key_exists('discipline', $object)) {
            $object['discipline'] = $discipline;
        }

        if ($current === null) {
            $this->applyNewObjectDefaults($object, $table);
        }

        $this->addDisciplineToNewObjects($object, $current ?? [], $relInfo, $table, $discipline, $position);
    }

    /**
     * Walks up the mapping tree and returns the path as an array of lowercase field segments.
     * Example: Locality.localityname → ['collectingevent', 'locality', 'localityname']
     */
    private function getMappingPath(Fields_mapping $mapping): array
    {
        $segments = [];
        $current  = $mapping;

        do {
            $parts      = explode('.', $current->api_table_field);
            $segments[] = strtolower($parts[1] ?? $parts[0]);

            // At the root node (no parent), also prepend the root table name
            if (!$current->parent) {
                $segments[] = strtolower($parts[0]);
            }

            $current = $current->parent ? Fields_mapping::find($current->parent) : null;
        } while ($current);

        return array_reverse($segments);
    }

    /**
     * Converts URI values in a fields array to numeric IDs for GET query params.
     * e.g. '/api/specify/geography/64/' → 64
     */
    private function urisToIds(array $fields): array
    {
        foreach ($fields as $key => $value) {
            if (is_string($value) && str_starts_with($value, '/api/specify/')) {
                if (preg_match('/\/(\d+)\/$/', $value, $matches)) {
                    $fields[$key] = $matches[1];
                }
            }
        }
        return $fields;
    }

    /** The defaults of the field being synced and the data of the schema: new nested objects get the defaults of their table. */
    private array $newObjectDefaults = [];
    private array $schemaTables = [];

    /** The discipline objects already fetched in this run (a discipline's trees do not change while it runs). */
    private array $disciplines = [];

    /**
     * A rank is stored as '{tree id}_{rank}' ("5_200": rank 200 of tree 5), but the tree id belongs to one discipline: each
     * discipline of a Specify database has its own geography, taxon... tree, with the same rank numbers. Taken as it is,
     * the mapping would look for (and create) the names in another discipline's tree as soon as a record belongs to a
     * different collection. So the tree is the one of the discipline of the record being written, when Specify tells it.
     */
    private function rankInDisciplineTree(?string $rankId, string $table, ?string $discipline, string $cookie_file, array $headers): ?string
    {
        if (!$rankId || !str_contains($rankId, '_') || !$discipline) {
            return $rankId;
        }

        if (empty($this->disciplines[$discipline])) {
            $fetched = json_decode($this->executeCurl(config('services.specify.url') . $discipline, null, $cookie_file, $headers), true);
            if (!is_array($fetched)) {
                return $rankId;
            }
            $this->disciplines[$discipline] = $fetched;
        }

        $tree = $this->disciplines[$discipline][strtolower($table) . 'treedef'] ?? null;
        if (!is_string($tree) || !preg_match('#/(\d+)/$#', $tree, $m)) {
            return $rankId;
        }

        [$mapped, $rank] = explode('_', $rankId, 2);
        if ($mapped !== $m[1]) {
            echo "  <span style='color:#2563eb'>" . htmlspecialchars($table) . ": the mapping names tree {$mapped}; the discipline of this record uses tree {$m[1]}, which is the one searched.</span><br>";
        }

        return $m[1] . '_' . $rank;
    }

    /**
     * Appends rank filter params to a fields array if rank_id is provided.
     * rank_id format: '{treedef_id}_{rankid}' (e.g. '5_200')
     */
    private function applyRankFilter(array $fields, ?string $rankId): array
    {
        if ($rankId && str_contains($rankId, '_')) {
            [$treedefId, $rankItemId] = explode('_', $rankId, 2);
            $fields['definitionitem__rankid']  = $rankItemId;
            $fields['definitionitem__treedef'] = $treedefId;
        }
        return $fields;
    }

    /**
     * Searches for a resource. Returns the URI if found, null otherwise.
     * Used for reverse FK groups where creation must wait for the parent.
     */
    private function searchResource($table, $fields, $headers, $cookie_file, ?string $rankId = null, ?string $discipline = null): ?string
    {
        $specify7URL  = config('services.specify.url');
        $rankId       = $this->rankInDisciplineTree($rankId, $table, $discipline, $cookie_file, $headers);
        $searchFields = $this->applyRankFilter($this->urisToIds($fields), $rankId);

        $url  = $specify7URL . '/api/specify/' . $table . '/?' . http_build_query($searchFields);
        echo "  GET $url<br>";
        $res  = $this->executeCurl($url, null, $cookie_file, $headers);
        $data = json_decode($res, true);

        return $data['objects'][0]['resource_uri'] ?? null;
    }

    /**
     * Searches for a resource by its fields. Creates it if not found.
     *
     * Field precedence in the create payload:
     *   1. Admin-configured required defaults ($requiredDefaults) — lowest priority
     *   2. Mapped/validated field values — override defaults if non-empty
     *   3. discipline / collection — always injected last
     *   4. definitionitem / definition — tree tables only
     *
     * Empty string and null values from the transcription are treated as absent,
     * so the configured default is applied (Specify rejects empty required fields).
     */
    private function findOrCreateResource(
        $table,
        $fields,
        $headers,
        $cookie_file,
        $discipline,
        $collectionUri,
        ?string $rankId = null,
        array $requiredDefaults = [],
        bool $dryRun = false
    ) {
        $specify7URL  = config('services.specify.url');
        $rankId       = $this->rankInDisciplineTree($rankId, $table, $discipline, $cookie_file, $headers);
        $searchFields = $this->applyRankFilter($this->urisToIds($fields), $rankId);

        $url  = $specify7URL . '/api/specify/' . $table . '/?' . http_build_query($searchFields);
        echo "  GET $url<br>";
        $res  = $this->executeCurl($url, null, $cookie_file, $headers);
        $data = json_decode($res, true);

        if (!empty($data['objects'])) {
            return $data['objects'][0]['resource_uri'];
        }

        // --- Build create payload ---

        // 1. Apply admin-configured defaults for this table (lowest priority)
        $createFields = [];
        foreach ($requiredDefaults as $key => $defaultValue) {
            [$defTable, $defField] = array_pad(explode('.', $key, 2), 2, null);
            if (!$defField) continue;
            if (strtolower($defTable) !== strtolower($table)) continue;
            if ($defaultValue === null || $defaultValue === '') continue; // a null/empty default is not usable
            $mappedValue = $fields[$defField] ?? null;
            if ($mappedValue === null || $mappedValue === '') {
                $createFields[$defField] = $defaultValue;
            }
        }

        // 2. Mapped/validated values override defaults (skip empty values)
        foreach ($fields as $k => $v) {
            if ($v !== null && $v !== '') {
                $createFields[$k] = $v;
            }
        }

        // 3. discipline and collection are always required
        $createFields['discipline'] = $discipline;
        $createFields['collection'] = $collectionUri;

        // 4. Resolve treedefitem for tree tables
        if ($rankId && str_contains($rankId, '_')) {
            [$treedefId, $rankItemId] = explode('_', $rankId, 2);

            $treedefTable   = $table . 'treedefitem';
            $treedefItemUrl = $specify7URL . '/api/specify/' . $treedefTable
                . '/?treedef=' . $treedefId . '&rankid=' . $rankItemId;
            $treedefRes     = $this->executeCurl($treedefItemUrl, null, $cookie_file, $headers);
            $treedefData    = json_decode($treedefRes, true);

            if (!empty($treedefData['objects'][0]['resource_uri'])) {
                $createFields['definitionitem'] = $treedefData['objects'][0]['resource_uri'];
                $createFields['definition']     = '/api/specify/' . $table . 'treedef/' . $treedefId . '/';
            }
        }

        echo "  POST payload: " . htmlspecialchars(json_encode($createFields)) . "<br>";

        if ($dryRun) {
            // a dry run writes nothing, not even the objects a value would need: the address stands in for the new one
            echo "  <span style='color:#b30000'>DRY RUN — $table not created (it would be).</span><br>";

            return '/api/specify/' . $table . '/0/';
        }

        $url_post = $specify7URL . '/api/specify/' . $table . '/';
        echo "  POST $url_post<br>";
        $out    = $this->executeCurl($url_post, json_encode($createFields), $cookie_file, $headers);
        $result = json_decode($out, true);

        if (empty($result['resource_uri'])) {
            echo "  <span style='color:red'>POST failed: " . htmlspecialchars($out) . "</span><br>";
        }

        return $result['resource_uri'] ?? null;
    }

    /**
     * Executes a cURL request. Sends POST if payload is provided, GET otherwise.
     */
    private function executeCurl($url, $payload = null, $cookie_file, $headers)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($payload) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * Recursively merges $target into $source.
     * Numeric arrays (e.g. collectors, preparations) are merged by ordernumber —
     * $position is the line's ordernumber (1-indexed, matching Specify's own
     * field), and must NOT be treated as an array index into $source[$key].
     * Confirmed experimentally: Specify's array position is not even a fixed
     * offset (e.g. ordernumber - 1) — it's the sorted rank among whichever
     * ordernumbers currently exist for that parent (sync collectors 2 and 3
     * only, with no 1, and ordernumber=2 lands at array index 0, not 1).
     * That position shifts as siblings are added/removed, so the only stable
     * way to find "the existing entry for this line" is to match on the
     * ordernumber field's value, never on its array position.
     */
    public function recursiveMerge(array &$source, array $target, int $position): void
    {
        foreach ($target as $key => $value) {
            if (is_array($value)) {
                if (isset($source[$key]) && is_array($source[$key]) && $this->isNumericArray($value)) {
                    foreach ($value as $item) {
                        $ordered       = \App\Services\ListLine::isOrdered($source[$key], $item);
                        $existingIndex = \App\Services\ListLine::find($source[$key], $item, $position);

                        if ($existingIndex !== null) {
                            $source[$key][$existingIndex] = array_merge($source[$key][$existingIndex], $item);
                            if ($ordered) {
                                $source[$key][$existingIndex]['ordernumber'] = $position;
                            }
                        } else {
                            if ($ordered) {
                                $item['ordernumber'] = $position;
                            }
                            $source[$key][] = $item;
                        }
                    }
                    ksort($source[$key]);
                    $source[$key] = array_values($source[$key]);
                } elseif (isset($source[$key]) && is_array($source[$key])) {
                    $this->recursiveMerge($source[$key], $value, $position);
                } else {
                    $source[$key] = $value;
                }
            } else {
                $source[$key] = $value;
            }
        }
    }

    private function isNumericArray(array $arr): bool
    {
        if ([] === $arr) return false;
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * The first name in the delta that is neither a field nor a relation of its table ("collectionobject.nonsense"), or
     * null. Tables the data model gives no fields for are not checked.
     */
    private function unknownDeltaKey(array $delta, array $relInfo, string $table): ?string
    {
        $known = $relInfo[strtolower($table)] ?? null;
        if (!$known || empty($known['#fields'])) {
            return null;
        }

        foreach ($delta as $key => $value) {
            $name = strtolower((string) $key);
            if (!isset($known[$name]) && !isset($known['#fields'][$name])) {
                return strtolower($table) . '.' . $name;
            }
            if (is_array($value)) {
                $rel   = $known[$name] ?? null;
                $child = ($rel && $rel['related'] !== '') ? $rel['related'] : $name;
                $inner = $this->unknownDeltaKey($value, $relInfo, $child);
                if ($inner) {
                    return $inner;
                }
            }
        }

        return null;
    }

    /**
     * The required defaults of the field for a table ("preparation.preptype" = the address of a type of preparation) fill
     * what a new nested object lacks: an object created from a line (a preparation, a collector...) is not made by a
     * search, so the defaults of the field were not applied to it. What the transcription gave is never replaced.
     */
    private function applyNewObjectDefaults(array &$object, string $table): void
    {
        foreach ($this->newObjectDefaults as $key => $default) {
            [$defTable, $defField] = array_pad(explode('.', (string) $key, 2), 2, null);
            if (!$defField || strtolower($defTable) !== $table) continue;
            if ($default === null || $default === '') continue;

            $field = strtolower($defField);
            if (array_key_exists($field, $object) && $object[$field] !== null && $object[$field] !== '') continue;

            $object[$field] = is_string($default) ? $this->castDefault($table, $field, $default) : $default;
        }
    }

    /** A yes/no default written as text ("true") goes as a boolean, as every other yes/no value does. */
    private function castDefault(string $table, string $field, string $value)
    {
        foreach ($this->schemaTables as $name => $schema) {
            if (strtolower($name) === $table && ($schema['properties'][$field]['type'] ?? '') === 'boolean') {
                return $this->toBoolean($value) ?? $value;
            }
        }

        return $value;
    }

    /** true/false for the usual ways of writing a yes/no value, null for anything else (it is then sent as it is). */
    private function toBoolean(string $value): ?bool
    {
        return match (mb_strtolower(trim($value))) {
            'true', '1', 'yes', 'y', 't', 'sim', 'ja', 'oui', 'si', 'sí' => true,
            'false', '0', 'no', 'n', 'f', 'nao', 'não', 'nej', 'non' => false,
            default => null,
        };
    }

    /**
     * Walks a delta array and for every field with format "date" in the openapi schema:
     *   - Counts non-empty, non-placeholder segments (ignores values like "DD", "MM")
     *   - Pads the date to YYYY-MM-DD format (Specify requirement)
     *   - Injects a companion {fieldname}precision field: 1=full, 2=year-month, 3=year only
     *
     * The delta is traversed recursively to handle nested objects (e.g. collectingevent).
     */
    private function applyDatePrecision(array $delta, array $apiSchema, string $tablePath = '', array $relInfo = []): array
    {
        foreach ($delta as $key => $value) {
            if (is_array($value)) {
                // Recurse into nested objects. The table of a nested object is the one the data model names for the
                // relation (determinations → determination); without it the relation's name is taken as the table.
                $rel   = $relInfo[strtolower($tablePath)][strtolower((string) $key)] ?? null;
                $child = ($rel && $rel['related'] !== '') ? $rel['related'] : (string) $key;
                if ($this->isNumericArray($value)) {
                    foreach ($value as $i => $item) {
                        if (is_array($item)) {
                            $delta[$key][$i] = $this->applyDatePrecision($item, $apiSchema, $child, $relInfo);
                        }
                    }
                } else {
                    $delta[$key] = $this->applyDatePrecision($value, $apiSchema, $child, $relInfo);
                }
                continue;
            }

            if (!is_string($value) || $value === '') continue;

            // Find which schema table this field belongs to by matching the parent key
            $tableSchema = null;
            foreach ($apiSchema as $tableName => $schema) {
                if (strtolower($tableName) === strtolower($tablePath)) {
                    $tableSchema = $schema;
                    break;
                }
            }

            if (!$tableSchema) continue;

            $fieldSchema = $tableSchema['properties'][strtolower($key)] ?? null;
            if (!$fieldSchema) continue;

            // a yes/no field takes a real boolean: the text "true" is refused by Specify (HTTP 500)
            if (($fieldSchema['type'] ?? '') === 'boolean') {
                $bool = $this->toBoolean($value);
                if ($bool !== null) {
                    $delta[$key] = $bool;
                }
                continue;
            }

            if (($fieldSchema['format'] ?? '') !== 'date') continue;

            // Field is a date — deduce precision from the segments present
            $precisionField = strtolower($key) . 'precision';

            // Split and filter out empty or placeholder segments (DD, MM, YYYY placeholders)
            $rawSegments  = explode('-', $value);
$validSegments = array_filter($rawSegments, function ($seg) {
    return $seg !== '' 
        && $seg !== '00'           // 00 = unknown, treat as absent
        && !preg_match('/^[A-Z]+$/', $seg);
});
            $validSegments = array_values($validSegments);
            $count         = count($validSegments);

            if ($count === 0) {
                // No valid date — remove this field from delta
                unset($delta[$key]);
                continue;
            }

            // Precision: 1=full date, 2=year-month, 3=year only
            $precision = match($count) {
                3       => 1,
                2       => 2,
                default => 3,
            };

            // Pad to full YYYY-MM-DD as required by Specify
            $year  = $validSegments[0] ?? '0000';
            $month = $validSegments[1] ?? '01';
            $day   = $validSegments[2] ?? '01';

            $delta[$key]          = sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
            $delta[$precisionField] = $precision;

            echo "  <span style='color:#2563eb'>Date field <b>$key</b>: "
                . htmlspecialchars($value) . " → {$delta[$key]} (precision=$precision, {$precisionField}={$precision})</span><br>";
        }

        return $delta;
    }

    /**
     * Parses a Netscape-format cookie file and returns an array of cookies.
     */
    /**
     * The CSRF token of the Specify at $url. A cookie jar may hold the cookies of more than one host, and the token of
     * another one makes Specify refuse the request ("CSRF verification failed"); when no cookie names the host, the
     * last token is used, as before.
     */
    private function csrfToken(array $cookies, string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $any = '';
        $token = '';
        foreach ($cookies as $c) {
            if ($c['name'] !== 'csrftoken') continue;
            $any = $c['value'];
            $domain = ltrim((string) preg_replace('/^#HttpOnly_/', '', $c['domain']), '.');
            if ($domain === $host || str_ends_with($host, '.' . $domain)) {
                $token = $c['value'];
            }
        }

        return $token !== '' ? $token : $any;
    }

    private function extractCookies($string): array
    {
        $cookies = [];
        foreach (explode("\n", $string) as $line) {
            if (isset($line[0]) && substr_count($line, "\t") == 6) {
                $tokens    = array_map('trim', explode("\t", $line));
                $cookies[] = ['domain' => $tokens[0], 'name' => $tokens[5], 'value' => $tokens[6]];
            }
        }
        return $cookies;
    }
}