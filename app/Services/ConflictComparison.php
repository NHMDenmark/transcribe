<?php

namespace App\Services;

use App\Models\Fields_mapping;
use App\Models\Project;
use App\Models\Validate_fields;

/**
 * Lines a validated value up, part by part, against what Specify holds right
 * now. Everything on the Specify side is read live from the API; nothing is
 * stored, so it can't go stale between the sync and the admin's decision.
 */
class ConflictComparison
{
    private array $cache = [];

    public function __construct(private SpecifyClient $client)
    {
    }

    /**
     * @return array{root: ?array, rootUrl: ?string, rows: array}
     *   rows: label, validated, specify (null = empty/unmapped), decision (different|equal|empty)
     */
    public function compare(Validate_fields $record, ?Project $project): array
    {
        $root = $this->rootRecord($record, $project);
        $checker = $project ? SyncConflictChecker::forProject($project) : new SyncConflictChecker();
        $field = $record->field;

        $rows = [];
        foreach ($this->validatedParts($record) as $index => $part) {
            $live = [];
            if ($root && $field) {
                $mappings = $field->mapping->where('part', (string) $index)->whereIn('type', ['write', 'pick']);
                foreach ($mappings as $m) {
                    $value = $this->valueAt($root, $this->segments($m), (int) $record->position);
                    if ($value !== null && trim($value) !== '') {
                        $live[] = $value;
                    }
                }
            }
            $live = array_values(array_unique($live));

            $decision = 'empty';
            foreach ($live as $v) {
                if ($checker->same($v, $part['value'])) {
                    $decision = $decision === 'different' ? 'different' : 'equal';
                } else {
                    $decision = 'different';
                }
            }

            $rows[] = [
                'label' => $part['label'],
                'validated' => $part['value'],
                'specify' => $live ? implode(' / ', $live) : null,
                'decision' => $decision,
            ];
        }

        $rootUrl = $root && isset($root['id'], $root['resource_uri']) && preg_match('#^/api/specify/([^/]+)/#', $root['resource_uri'], $m)
            ? $this->client->baseUrl() . '/specify/view/' . $m[1] . '/' . $root['id'] . '/'
            : null;

        return ['root' => $root, 'rootUrl' => $rootUrl, 'rows' => $rows];
    }

    /** The record the sync writes into: the project's sync root, matched on the specimen id (same rule as the sync). */
    private function rootRecord(Validate_fields $record, ?Project $project): ?array
    {
        if (!$project) {
            return null;
        }

        $table = strtolower($project->sync_root_table ?: 'collectionobject');
        $column = strtolower($project->sync_root_field ?: 'catalognumber');
        $value = RootLookup::value($record->specimen_id);

        $data = $this->get('/api/specify/' . $table . '/?' . RootLookup::query($column, $value));

        return RootLookup::pick($column, $data['objects'] ?? [], $project->specify_collection)[0];
    }

    /** One entry per stored part of the validated value, labelled when the field defines labels. */
    public function validatedParts(Validate_fields $record): array
    {
        $field = $record->field;
        $value = (string) $record->value;
        $char = $field->explode_char ?? '';

        if (!$field || $char === '' || $field->concatenate_parts) {
            return [['label' => null, 'value' => $value]];
        }

        $values = explode($char, $value);
        $labels = $field->explode_fields !== null && $field->explode_fields !== '' ? explode($char, $field->explode_fields) : [];

        $out = [];
        foreach ($values as $i => $v) {
            $out[] = ['label' => count($labels) === count($values) ? $labels[$i] : null, 'value' => $v];
        }

        return $out;
    }

    /** Path below the root record for a mapping row, e.g. [collectingevent, locality, localityname]. */
    private function segments(Fields_mapping $mapping): array
    {
        $segments = [];
        $current = $mapping;

        do {
            $parts = explode('.', $current->api_table_field);
            $segments[] = strtolower($parts[1] ?? $parts[0]);
            if (!$current->parent) {
                $segments[] = strtolower($parts[0]);
            }
            $current = $current->parent ? Fields_mapping::find($current->parent) : null;
        } while ($current);

        $segments = array_reverse($segments);
        array_shift($segments); // the root table itself

        return $segments;
    }

    private function valueAt(array $root, array $segments, int $position): ?string
    {
        $current = $root;

        foreach ($segments as $segment) {
            $current = $this->follow($current, $position);
            if (!is_array($current)) {
                return null;
            }
            $current = $current[$segment] ?? null;
        }

        return is_scalar($current) ? (string) $current : null;
    }

    /** A bare URI becomes the record behind it; a dependent list becomes the line at this position. */
    private function follow(mixed $value, int $position): mixed
    {
        if (is_string($value) && str_starts_with($value, '/api/specify/')) {
            return $this->get($value);
        }

        if (is_array($value) && $value !== [] && array_keys($value) === range(0, count($value) - 1)) {
            foreach ($value as $item) {
                if (is_array($item) && (int) ($item['ordernumber'] ?? -1) === $position) {
                    return $item;
                }
            }

            return null;
        }

        return $value;
    }

    private function get(string $path): ?array
    {
        if (!array_key_exists($path, $this->cache)) {
            $this->cache[$path] = $this->client->get($path);
        }

        return $this->cache[$path];
    }
}
