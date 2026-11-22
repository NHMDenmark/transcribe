<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fields;
use App\Models\Field_options;
use App\Models\Collaborative_fields;
use App\Models\Validate_fields;
use App\Models\Fields_trees;
use App\Models\Specify_fields;
use App\Models\Dialogs;
use App\Models\Language;
use App\Models\Fields_mapping;

use DB;

class fieldsController extends Controller
{
    public

    function show()
    {


        $fields = Fields::with('options')->orderBy('position')->get();

        $fields_dialogs = Dialogs::where('identifier', 'LIKE', 'fields_%')->get();

        $api_schema = API_get_schema();

        return view('fields.show', compact('fields', 'fields_dialogs', 'api_schema'));
    }


    public function save_order(Request $request)
    {

        parse_str((string) $request->id, $order_array);

        // the fields in the order they were dropped; an id that does not exist is skipped
        foreach (array_values((array) ($order_array['item'] ?? [])) as $position => $field_id) {
            $field = Fields::find($field_id);
            if (!$field) {
                continue;
            }
            $field->position = $position;
            $field->save();
        }

        return response()->json(['message' => 'Fields were sorted']);
    }

    public

    function save(Request $request)
    {

        //		echo $request->field_id;

        if ($request->field_id != '') {
            $field = Fields::find($request->field_id);
        } else {
            $field = new Fields;
        }

        $field->name = $request->name;
        //			$field->question = $request->question;
        //			$field->text = $request->text;
        $field->type = $request->type;
        $field->explode_fields = $request->explode_fields;
        $field->explode_char = $request->explode_char;
        $field->explode_save_direction = $request->explode_save_direction;
        $field->autocomplete = $request->autocomplete;
        $field->choices_separator = $request->choices_separator;
        $this->clear_what_the_type_does_not_use($field);
        $field->required = $request->required;
        $field->deducted = $request->deducted;
        $field->have_doubts = $request->have_doubts;
        $field->multiple = $request->multiple;
        $field->option_values = $request->option_values;
        if ($field->type === 'date') {
            $field->option_values = null;   // a date has no list of options or suggestions
        }
        // a field that chooses from a list: the list comes from the options table of the form; option_values
        // then only mirrors the values (one per line), for whatever still reads that column
        $choices = $this->parse_choices($request, $field);
        if ($choices !== null) {
            $field->option_values = implode("\n", array_column($choices, 'value'));
        }

        // a separator that is inside one of the options could not split the saved value back: nothing is saved
        if ($clash = $field->separatorClashesWith(array_column($choices ?? [], 'value'))) {
            return redirect('/fields')->with('error', 'The separator "' . $field->choices_separator . '" is part of the option "' . $clash . '", so a joined value could not be split back. Choose another separator.');
        }
        $field->min_user_level = $request->min_user_level;
        $field->na_defaults = $request->na_defaults ?: null;
        $field->concatenate_parts = $request->has('concatenate_parts') ? 1 : 0;
        $field->save();
        $this->sync_options($field, $choices);

        //save the dialogs for each language
        $dialogs = array('name', 'question', 'text', 'helper');
        $languages = Language::get();

        foreach ($dialogs as $dialog) {
            foreach ($languages as $language) {
                $dialog_identifier = 'fields_' . $dialog . '_' . $field->id;
                $dialog_field_form_name = 'fields_' . $dialog . '_' . $language->iso2;
                $dialog_field = Dialogs::where('identifier', '=', $dialog_identifier)->where('language', '=', $language->iso2)->first();
                if (!isset($dialog_field)) {
                    $dialog_field = new Dialogs;
                    $dialog_field->identifier = $dialog_identifier;
                    $dialog_field->language = $language->iso2;
                }
                $dialog_field->text = $request->$dialog_field_form_name;
                $dialog_field->save();
            }
        }

        $field->projects()->syncWithoutDetaching(0);


        return redirect('/fields')->with('success', 'Form was submitted');
    }

    public function delete_field(Request $request)
    {
        $field = Fields::find($request->field_id)->delete();

        return response()->json(['message' => ' Field was deleted']);
    }

    public function get_children($id_tree, $parent_id, $json)
    {

        $children_query = Fields_trees::where('parent_id', '=', $parent_id)->where('id_tree', '=', $id_tree)->get();
        foreach ($children_query as $row) {
            $children = '{label: "' . str_replace('"', ' ', $row->value) . '",value: "' . $row->id_field . '",children:[';
            $json = $json . $children;
            $json = $this->get_children($id_tree, $row->id_field, $json);
            $json .= "]},";
        }
        return $json;
    }

    public function update_tree_options()
    {
        ini_set('max_execution_time', 0); //0=NOLIMIT		

        $tree_fields = Fields::where('type', '=', 'tree')->get();

        foreach ($tree_fields as $tree_field) {
            //geography tree field options
            $json = "[";
            $json = $this->get_children($tree_field->id, 1, $json);
            $json = str_replace(',children:[]}', '}', $json);

            $json .= "]";


            $tree_field->option_values = $json;
            $tree_field->save();
        }
    }

    public function fill_options_from_current(Request $request)
    {
        $field_id = $request->field_id;
        $values = Specify_fields::where('field_id', '=', $field_id)->distinct()->pluck('value')->toArray();

        //print_r($values);

        $values = implode($values, ';');

        return $values;
    }

public function api_field_edit(Request $request)
{
    $field_id = $request->input('field_id');
    $levels   = $request->input('levels', []);

    DB::transaction(function () use ($field_id, $levels, $request) {

        Fields_mapping::where('field_id', $field_id)->delete();

        $saveLevel = function ($levels, $parentId = null) use (&$saveLevel, $field_id) {
            foreach ($levels as $level) {
                $new = Fields_mapping::create([
                    'field_id'        => $field_id,
                    'part'            => $level['part'],
                    'api_table_field' => $level['param'],
                    'type'            => $level['action'],
                    'rank_id'         => $level['rank_id'] ?? null,
                    'parent'          => $parentId,
                ]);

                if (!empty($level['children'])) {
                    $saveLevel($level['children'], $new->id);
                }
            }
        };

        // Build tree from flat list
        $tree  = [];
        $stack = [];

        foreach ($levels as $nodeId => $lvl) {
            $lvl['children'] = [];
            $depth = $lvl['depth'];

            while (count($stack) > $depth) {
                array_pop($stack);
            }

            if (empty($stack)) {
                $tree[]  = $lvl;
                $stack[] = &$tree[count($tree) - 1];
            } else {
                $parent               = &$stack[count($stack) - 1];
                $parent['children'][] = $lvl;
                $stack[]              = &$parent['children'][count($parent['children']) - 1];
            }
        }

        $saveLevel($tree);

        // Save field-level settings
        $field = Fields::find($field_id);
        $field->required_defaults = !empty($request->input('required_defaults', []))
                                    ? $request->input('required_defaults')
                                    : null;
        $field->concatenate_parts = $request->boolean('concatenate_parts');
        $field->save();
    });

    return redirect()->back()->with('success', 'Mapping saved.');
}





    public function getTableRanks(Request $request, $tableName)
    {
        $specify7URL = config('services.specify.url');
        $session_id = session()->getId();
        $cookie_file = specify_cookie_file();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // 1. Fetch all disciplines to build an ID -> Name map
        curl_setopt($ch, CURLOPT_URL, $specify7URL . "/api/specify/discipline/");
        $discResponse = json_decode(curl_exec($ch), true);
        $disciplines = [];
        if (!empty($discResponse['objects'])) {
            foreach ($discResponse['objects'] as $d) {
                $disciplines[$d['resource_uri']] = $d['name'];
            }
        }

        // 2. Fetch tree definitions
        $url = $specify7URL . "/api/specify/" . strtolower($tableName) . "treedef/";
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $final_ranks = [];
        if (!empty($data['objects'])) {
            foreach ($data['objects'] as $definition) {
                // Resolve discipline name via the map built above
                $disciplineName = $disciplines[$definition['discipline']] ?? 'Global/Unknown';

                if (!empty($definition['treedefitems'])) {
                    foreach ($definition['treedefitems'] as $item) {
                        $final_ranks[] = [
                            'rank' => $definition['id'] . '_' . $item['rankid'], // Ex: "1_200"
                            'name' => "[$disciplineName] " . $item['name'],
                            'discipline' => $disciplineName,
                            'pure_rank' => $item['rankid'] // kept for reference
                        ];
                    }
                }
            }
        }

        // Sort by discipline then by rank
        return response()->json(collect($final_ranks)->sortBy(['discipline', 'rank'])->values());
    }

    /**
     * Returns the required fields of a Specify table that the admin
     * should fill in as defaults in the mapping.
     *
     * Automatically excludes:
     *   - system-managed fields (timestamps, version, collectionmemberid)
     *   - relations managed by the ingestion code (discipline, collection,
     *     definitionitem, definition, createdbyagent, modifiedbyagent)
     *   - tree fields managed by the rank_id mechanism (rankid, definition,
     *     definitionitem) — already handled in findOrCreateResource
     *
     * GET /api_get-table-required-fields/{table}
     * Response: [{"name": "source", "type": "java.lang.String", "length": 64}, ...]
     */
    public function getTableRequiredFields(Request $request, $tableName)
    {
        // CollectionObject is never created by ingestion — always pre-existing
        if (strtolower($tableName) === 'collectionobject') {
            return response()->json([]);
        }

        $specify7URL = config('services.specify.url');
        $session_id  = session()->getId();
        $cookie_file = specify_cookie_file();

        $alwaysIgnore = [
            'timestampcreated',
            'timestampmodified',
            'version',
            'collectionmemberid',
            'rankid',
        ];

        $ignoredRelations = [
            'discipline',
            'collection',
            'definitionitem',
            'definition',
            'createdbyagent',
            'modifiedbyagent',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $specify7URL . '/context/datamodel.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $raw = curl_exec($ch);
        curl_close($ch);

        $datamodel = json_decode($raw, true) ?? [];
        $result    = [];

        foreach ($datamodel as $tableDef) {
            if (
                strtolower($tableDef['classname'] ?? '') !== strtolower($tableName)
                && strtolower($tableDef['table']     ?? '') !== strtolower($tableName)
            ) {
                continue;
            }

            foreach ($tableDef['fields'] ?? [] as $field) {
                if (empty($field['required']))                             continue;
                if (in_array(strtolower($field['name']), $alwaysIgnore))  continue;

                $result[] = [
                    'name'   => $field['name'],
                    'type'   => $field['type'],
                    'length' => $field['length'] ?? null,
                ];
            }

            // The required relations too (Preparation needs its type, an Accession its division): a database refuses the
            // new object without them, and no field of the mapping says what to put there.
            $thisTable = strtolower($tableDef['table'] ?? $tableName);
            foreach ($tableDef['relationships'] ?? [] as $rel) {
                if (empty($rel['required']))                                                  continue;
                if (in_array(strtolower($rel['name']), $ignoredRelations))                     continue;
                if (!in_array(strtolower($rel['type'] ?? ''), ['many-to-one', 'one-to-one', 'zero-to-one'])) continue;

                // the parent of a dependent object (the collection object of a preparation, the collecting event of a
                // collector) is set by Specify from where the object is written: it is not a choice
                $isParentLink = false;
                foreach ($datamodel as $other) {
                    if (strtolower($other['table'] ?? '') !== strtolower($rel['relatedModelName'] ?? '')) continue;
                    foreach ($other['relationships'] ?? [] as $inverse) {
                        if (!empty($inverse['dependent']) && strtolower($inverse['type'] ?? '') === 'one-to-many'
                            && strtolower($inverse['relatedModelName'] ?? '') === $thisTable) {
                            $isParentLink = true;
                        }
                    }
                }
                if ($isParentLink) continue;

                $result[] = [
                    'name'         => $rel['name'],
                    'type'         => 'relation',
                    'length'       => null,
                    'relatedModel' => $rel['relatedModelName'] ?? '',
                ];
            }


            break;
        }

        return response()->json($result);
    }
    /**
     * The existing objects of a table, to choose the default of a required relation from (the types of preparation of the
     * collection, the divisions...). The value saved is the address of the object.
     *
     * GET /api_get-related-options/{table}
     * Response: [{"uri": "/api/specify/preptype/5/", "label": "Skin"}, ...]
     */
    public function getRelatedOptions(Request $request, $tableName)
    {
        $table = strtolower(preg_replace('/[^A-Za-z]/', '', (string) $tableName));
        $url   = config('services.specify.url') . '/api/specify/' . $table . '/';
        $read  = function (string $query) use ($url) {
            $ch = curl_init($url . '?' . $query);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, specify_cookie_file());
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $data = json_decode((string) curl_exec($ch), true);
            curl_close($ch);

            return $data['objects'] ?? [];
        };

        // the ones of the collection the session is logged in to; a table that is not scoped to one has all of them
        $objects = $read('domainfilter=true&limit=200') ?: $read('limit=200');

        $options = [];
        foreach ($objects as $object) {
            if (empty($object['resource_uri'])) continue;
            $label = null;
            foreach (['name', 'fullname', 'title', 'accessionnumber', 'lastname', 'text1'] as $key) {
                if (!empty($object[$key]) && is_string($object[$key])) { $label = $object[$key]; break; }
            }
            $options[] = ['uri' => $object['resource_uri'], 'label' => $label ?? $object['resource_uri']];
        }
        usort($options, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return response()->json($options);
    }

    /**
     * Types that do not split into parts (only text and date do) or have no autocomplete (the ones that
     * choose from a list, and date) keep nothing of those properties, so a field changed from one type to
     * another leaves no separator or autocomplete behind for the transcription form or the sync to read.
     * Other types (e.g. tree) are left as they are.
     */
    private function clear_what_the_type_does_not_use(Fields $field): void
    {
        if (in_array($field->type, ['textarea', 'select', 'radio', 'checkboxes'], true)) {
            $field->explode_fields = null;
            $field->explode_char = null;
            $field->explode_save_direction = null;
        }

        if (in_array($field->type, ['date', 'select', 'radio', 'checkboxes'], true)) {
            $field->autocomplete = 0;
        }

        // only a checkboxes field can save its ticked options as one value
        if ($field->type !== 'checkboxes') {
            $field->choices_separator = null;
        }
    }
    /**
     * The options a select / radio / checkboxes field is saved with, in order: [value, labels per language],
     * or null for any other type. They come from the table of the form (choices_json); a form or script that
     * sends only option_values (one per line) still works, keeping the labels of the options that stay.
     * Blank values and repeated values are dropped; only the known languages' labels are kept.
     */
    private function parse_choices(Request $request, Fields $field): ?array
    {
        if (!$field->isChoice()) {
            return null;
        }

        $languages = Language::pluck('iso2')->all();
        $rows = [];
        $seen = [];
        $json = $request->input('choices_json');

        if (is_string($json) && $json !== '') {
            foreach ((array) json_decode($json, true) as $row) {
                $value = trim((string) ($row['value'] ?? ''));
                if ($value === '' || isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;

                $labels = [];
                foreach ($languages as $language) {
                    $label = trim((string) ($row['labels'][$language] ?? ''));
                    if ($label !== '') {
                        $labels[$language] = $label;
                    }
                }
                $rows[] = ['value' => $value, 'labels' => $labels];
            }

            return $rows;
        }

        $existing = $field->exists ? Field_options::where('field_id', $field->id)->get()->keyBy('value') : collect();
        foreach (Fields::linesToValues($request->option_values) as $value) {
            $rows[] = ['value' => $value, 'labels' => $existing->get($value)?->labels ?? []];
        }

        return $rows;
    }

    /** Makes the options table match the list: new ones added, kept ones updated (by value), the rest removed. */
    private function sync_options(Fields $field, ?array $choices): void
    {
        if ($choices === null) {
            Field_options::where('field_id', $field->id)->delete();   // this type has no list of options

            return;
        }

        $existing = Field_options::where('field_id', $field->id)->get()->keyBy('value');
        $kept = [];

        foreach ($choices as $position => $choice) {
            $option = $existing->get($choice['value']) ?? new Field_options();
            $option->field_id = $field->id;
            $option->value = $choice['value'];
            $option->position = $position;
            $option->labels = $choice['labels'] ?: null;
            $option->save();
            $kept[] = $option->id;
        }

        Field_options::where('field_id', $field->id)->whereNotIn('id', $kept)->delete();
    }

    /** For "fill with what was already submitted": the distinct values people have submitted or validated for the field. */
    public function submitted_values($id)
    {
        $values = Collaborative_fields::where('field_id', $id)->where('status', '>', 0)->distinct()->limit(2000)->pluck('value')
            ->merge(Validate_fields::where('field_id', $id)->where('status', '>', 0)->distinct()->limit(2000)->pluck('value'))
            ->map(fn($v) => trim((string) $v))
            ->filter(fn($v) => $v !== '' && $v !== 'n.a.')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->take(1000);

        return response()->json(['values' => $values]);
    }
}
