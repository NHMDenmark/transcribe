<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Field_options;
use App\Models\Fields;
use App\Models\Language;
use App\Models\Project;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Validate_fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The options of a select / radio / checkboxes field: a value that is saved (no language) and, per language,
 * what is shown instead.
 */
class FieldOptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['en', 'da'] as $iso) {
            $language = new Language();
            $language->iso2 = $iso;
            $language->save();
        }
        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $this->admin = $this->user('admin');
    }

    protected function tearDown(): void
    {
        unset($_COOKIE['active_language']);
        parent::tearDown();
    }

    private function user(string $role = 'user'): User
    {
        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = ucfirst($role);
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function save(string $type, array $overrides = [], ?int $fieldId = null): Fields
    {
        $data = [
            'field_id' => $fieldId ?? '', 'name' => 'month', 'type' => $type,
            'required' => '1', 'deducted' => '0', 'have_doubts' => '0', 'multiple' => '0', 'min_user_level' => '5',
            'fields_name_en' => 'Month', 'fields_question_en' => 'Q', 'fields_text_en' => 'T', 'fields_helper_en' => 'H',
            'fields_name_da' => 'Måned', 'fields_question_da' => 'Q', 'fields_text_da' => 'T', 'fields_helper_da' => 'H',
        ];
        $this->actingAs($this->admin)->post('/field_edit', array_merge($data, $overrides))->assertRedirect('/fields');

        return Fields::latest('id')->firstOrFail()->refresh();
    }

    private function choices(array $rows): string
    {
        return json_encode($rows);
    }

    // ---- the options of a field ------------------------------------------------------------------

    public function test_the_options_are_saved_with_a_value_and_a_label_per_language_in_order(): void
    {
        $f = $this->save('select', ['choices_json' => $this->choices([
            ['value' => '1', 'labels' => ['en' => 'January', 'da' => 'Januar']],
            ['value' => '2', 'labels' => ['en' => 'February']],
            ['value' => '3', 'labels' => []],
        ])]);

        $rows = $f->options;
        $this->assertSame(['1', '2', '3'], $rows->pluck('value')->all());
        $this->assertSame([0, 1, 2], $rows->pluck('position')->all());

        // the label in a language, and the value where there is none
        $this->assertSame('Januar', $rows[0]->label('da'));
        $this->assertSame('February', $rows[1]->label('en'));
        $this->assertSame('2', $rows[1]->label('da'));
        $this->assertSame('3', $rows[2]->label('en'));
        // what the field mirrors in its old column: the values, one per line
        $this->assertSame("1\n2\n3", $f->option_values);
    }

    public function test_choices_gives_the_form_the_label_of_the_visitors_language_and_the_value_to_save(): void
    {
        $f = $this->save('radio', ['choices_json' => $this->choices([['value' => '1', 'labels' => ['da' => 'Januar', 'en' => 'January']], ['value' => '2', 'labels' => []]])]);

        $this->assertSame([['value' => '1', 'label' => 'Januar'], ['value' => '2', 'label' => '2']], $f->choices('da'));
        $this->assertSame([['value' => '1', 'label' => 'January'], ['value' => '2', 'label' => '2']], $f->choices('en'));
        // a language nobody wrote labels for shows the values
        $this->assertSame('1', $f->choices('fr')[0]['label']);
    }

    public function test_blank_and_repeated_values_are_dropped_and_unknown_languages_ignored(): void
    {
        $f = $this->save('checkboxes', ['choices_json' => $this->choices([
            ['value' => ' A ', 'labels' => ['en' => ' First ', 'fr' => 'Premier', 'da' => '']],
            ['value' => '', 'labels' => ['en' => 'no value']],
            ['value' => 'A', 'labels' => ['en' => 'repeat']],
            ['value' => 'B', 'labels' => []],
        ])]);

        $this->assertSame(['A', 'B'], $f->options->pluck('value')->all());
        $this->assertSame(['en' => 'First'], $f->options[0]->labels);   // trimmed; French is not a language here; a blank label is none
    }

    public function test_editing_keeps_the_options_that_stay_updates_their_labels_and_removes_the_rest(): void
    {
        $f = $this->save('select', ['choices_json' => $this->choices([
            ['value' => 'a', 'labels' => ['en' => 'Alpha']], ['value' => 'b', 'labels' => []], ['value' => 'c', 'labels' => []],
        ])]);
        $idOfA = $f->options[0]->id;

        $this->save('select', ['choices_json' => $this->choices([
            ['value' => 'c', 'labels' => []], ['value' => 'a', 'labels' => ['en' => 'Alpha', 'da' => 'Alfa']], ['value' => 'd', 'labels' => []],
        ])], $f->id);

        $rows = $f->fresh()->options;
        $this->assertSame(['c', 'a', 'd'], $rows->pluck('value')->all());
        $this->assertSame($idOfA, $rows->firstWhere('value', 'a')->id);      // an option that stays is the same row
        $this->assertSame('Alfa', $rows->firstWhere('value', 'a')->label('da'));
        $this->assertSame(3, Field_options::where('field_id', $f->id)->count());
    }

    public function test_a_long_list_goes_through_as_one_field(): void
    {
        $rows = [];
        foreach (range(1, 700) as $i) {
            $rows[] = ['value' => 'Country ' . $i, 'labels' => ['en' => 'Country ' . $i, 'da' => 'Land ' . $i]];
        }

        $f = $this->save('select', ['choices_json' => $this->choices($rows)]);

        $this->assertSame(700, $f->options()->count());
        $this->assertSame('Land 700', Field_options::where('field_id', $f->id)->orderByDesc('position')->first()->label('da'));
    }

    public function test_a_form_that_only_sends_the_text_still_works_and_keeps_the_labels_of_what_stays(): void
    {
        $f = $this->save('select', ['choices_json' => $this->choices([['value' => 'a', 'labels' => ['da' => 'Alfa']], ['value' => 'b', 'labels' => []]])]);

        // no table, just option_values (the old form, or a script)
        $this->save('select', ['option_values' => "b\na\n\nc\na"], $f->id);

        $rows = $f->fresh()->options;
        $this->assertSame(['b', 'a', 'c'], $rows->pluck('value')->all());
        $this->assertSame('Alfa', $rows->firstWhere('value', 'a')->label('da'));
    }

    public function test_the_options_belong_to_the_types_that_choose_from_a_list(): void
    {
        $f = $this->save('select', ['choices_json' => $this->choices([['value' => 'a', 'labels' => []]])]);
        $this->assertSame(1, $f->options()->count());

        // turned into a text field: it has no options table any more (its old values stay as suggestions)
        $this->save('text', ['option_values' => "a"], $f->id);
        $this->assertSame(0, $f->options()->count());
        $this->assertSame('a', $f->fresh()->option_values);
    }

    public function test_deleting_a_field_deletes_its_options(): void
    {
        $f = $this->save('radio', ['choices_json' => $this->choices([['value' => 'a', 'labels' => []], ['value' => 'b', 'labels' => []]])]);

        $this->actingAs($this->admin)->post('/field_delete', ['field_id' => $f->id])->assertOk();

        $this->assertSame(0, Field_options::where('field_id', $f->id)->count());
    }

    public function test_a_field_without_option_rows_offers_the_lines_of_its_old_list(): void
    {
        $f = new Fields();
        $f->name = 'old';
        $f->type = 'radio';
        $f->position = 1;
        $f->multiple = 0;
        $f->option_values = "Stick\nFork";
        $f->save();

        $this->assertSame([['value' => 'Stick', 'label' => 'Stick'], ['value' => 'Fork', 'label' => 'Fork']], $f->choices('da'));
    }

    // ---- the migration ------------------------------------------------------------------------------

    public function test_the_migration_turns_the_old_lists_into_options_without_labels(): void
    {
        $make = function (string $type, ?string $list) {
            $f = new Fields();
            $f->name = $type . uniqid();
            $f->type = $type;
            $f->position = 1;
            $f->multiple = 0;
            $f->option_values = $list;
            $f->save();

            return $f;
        };
        $radio = $make('radio', "Stick\n  Fork \n\nStick\r\nClub");
        $text = $make('text', "a suggestion");
        Field_options::query()->delete();

        $migration = require base_path('database/migrations/2026_11_04_100000_create_field_options.php');
        $migration->seedFromOptionValues();

        $this->assertSame(['Stick', 'Fork', 'Club'], Field_options::where('field_id', $radio->id)->orderBy('position')->pluck('value')->all());
        $this->assertNull(Field_options::where('field_id', $radio->id)->first()->labels);
        $this->assertSame(0, Field_options::where('field_id', $text->id)->count());   // only lists that choose
    }

    // ---- fill with what was submitted ---------------------------------------------------------------

    public function test_the_values_already_submitted_can_be_offered_as_options(): void
    {
        $f = $this->save('text', []);
        foreach ([['Spikes', 1], ['Ridges', 1], ['Spikes', 2], ['', 1], ['n.a.', 1], ['Gone', 0]] as [$value, $status]) {
            $c = new Collaborative_fields();
            $c->value = $value;
            $c->position = 0;
            $c->specimen_id = '1';
            $c->field_id = $f->id;
            $c->user_id = '1';
            $c->status = $status;
            $c->points = 20;
            $c->save();
        }
        $v = new Validate_fields();
        $v->value = 'Grooves';
        $v->position = 0;
        $v->specimen_id = '1';
        $v->field_id = $f->id;
        $v->user_id = 1;
        $v->status = 2;
        $v->save();

        $values = $this->actingAs($this->admin)->getJson('/field_submitted_values/' . $f->id)->assertOk()->json('values');

        $this->assertSame(['Grooves', 'Ridges', 'Spikes'], $values);   // distinct, no blanks or n.a., none deleted, in order
        $this->actingAs($this->user('user'))->getJson('/field_submitted_values/' . $f->id)->assertForbidden();
    }

    // ---- the form of the field ------------------------------------------------------------------------

    private function xpath(string $url): \DOMXPath
    {
        $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new \DOMXPath($doc);
    }

    public function test_the_form_has_the_table_of_options_with_a_column_per_language(): void
    {
        $x = $this->xpath('/fields');
        $group = '//div[@id="field_Modal"]//*[@id="choices_group"]';

        $this->assertSame(1, $x->query($group)->length);
        $this->assertSame(1, $x->query($group . '//tbody[@id="choices_body"]')->length);
        $this->assertSame(1, $x->query($group . '//input[@type="hidden"][@name="choices_json"]')->length);
        // a column for each language
        $this->assertSame(1, $x->query($group . '//thead//th[normalize-space()="en"]')->length);
        $this->assertSame(1, $x->query($group . '//thead//th[normalize-space()="da"]')->length);
        // the three ways to fill it
        foreach (['choice_add', 'choice_import_toggle', 'choice_fill'] as $id) {
            $this->assertSame(1, $x->query($group . '//button[@id="' . $id . '"]')->length, $id);
        }
        // the table's own inputs are never named: only the JSON is sent
        $this->assertSame(0, $x->query($group . '//tbody//input[@name]')->length);
    }

    public function test_the_edit_button_carries_the_options_with_their_labels(): void
    {
        $f = $this->save('radio', ['choices_json' => $this->choices([['value' => '1', 'labels' => ['da' => 'Januar']], ['value' => '2', 'labels' => []]])]);

        $button = $this->xpath('/fields')->query('//button[@data-field_id="' . $f->id . '"][@data-choices]')->item(0);
        $data = json_decode($button->getAttribute('data-choices'), true);

        $this->assertSame([['value' => '1', 'labels' => ['da' => 'Januar']], ['value' => '2', 'labels' => []]], $data);
    }

    public function test_the_form_script_builds_sends_and_protects_the_table(): void
    {
        $html = $this->actingAs($this->admin)->get('/fields')->assertOk()->getContent();

        foreach (['function addChoiceRow', 'function gatherChoices', 'function importLines', 'choices_json', 'field_submitted_values'] as $piece) {
            $this->assertStringContainsString($piece, $html, $piece);
        }
        // the table is for list fields; the plain box is not sent for them
        $this->assertStringContainsString("\$j('#choices_json').prop('disabled', !isChoice);", $html);
        $this->assertStringContainsString("group.find('textarea').prop('disabled', type === 'date' || isChoice);", $html);
    }

    // ---- the transcription form -----------------------------------------------------------------------

    private function specimenWithField(string $type, array $options): array
    {
        $project = new Project();
        $project->name = 'P';
        $project->user_id = 0;
        $project->active = 1;
        $project->field_view_default = 'all';
        $project->save();

        $field = $this->save($type, ['choices_json' => $this->choices($options), 'required' => '0']);
        $project->fields()->attach($field->id);
        Specimen::create(['specimen_id' => '999111', 'project_id' => $project->id, 'images' => 'a.jpg', 'min_user_level' => 5]);

        return [$project, $field];
    }

    private function formAs(User $user, Project $project, string $language): string
    {
        $_COOKIE['active_language'] = $language;
        $this->app->forgetInstance(\App\Services\DialogTextRepository::class);

        return $this->actingAs($user)->get('/projects/' . $project->id . '/999111')->assertOk()->getContent();
    }

    /** The options of the select, as the form shows them: [value => text]. One request per test (the page's language is fixed by the first). */
    private function selectOptionsShownIn(string $language): array
    {
        [$project] = $this->specimenWithField('select', [
            ['value' => '1', 'labels' => ['en' => 'January', 'da' => 'Januar']],
            ['value' => '2', 'labels' => ['en' => 'February']],
        ]);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $this->formAs($this->user('user'), $project, $language));

        $shown = [];
        foreach ((new \DOMXPath($doc))->query('//select[starts-with(@name,"field[")]/option') as $option) {
            $shown[$option->getAttribute('value')] = trim($option->textContent);
        }

        return $shown;
    }

    public function test_the_volunteer_sees_the_danish_labels_and_the_value_where_there_is_none(): void
    {
        $this->assertSame(['1' => 'Januar', '2' => '2'], $this->selectOptionsShownIn('da'));
    }

    public function test_the_volunteer_sees_the_english_labels_while_the_value_is_what_is_saved(): void
    {
        $this->assertSame(['1' => 'January', '2' => 'February'], $this->selectOptionsShownIn('en'));
    }
    public function test_radio_buttons_and_checkboxes_show_labels_too_and_come_back_marked(): void
    {
        $options = [['value' => '1', 'labels' => ['da' => 'Januar']], ['value' => '2', 'labels' => ['da' => 'Februar']]];
        $volunteer = $this->user('user');

        foreach (['radio', 'checkboxes'] as $type) {
            Fields::query()->delete();
            Field_options::query()->delete();
            [$project, $field] = $this->specimenWithField($type, $options);

            $c = new Collaborative_fields();
            $c->value = '2';
            $c->position = 1;
            $c->specimen_id = '999111';
            $c->field_id = $field->id;
            $c->user_id = (string) $volunteer->id;
            $c->project_id = $project->id;
            $c->status = 1;
            $c->points = 20;
            $c->save();

            $html = $this->formAs($volunteer, $project, 'da');
            $inputType = $type === 'radio' ? 'radio' : 'checkbox';
            $doc = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
            $x = new \DOMXPath($doc);
            $input = fn(string $value) => '//input[@type="' . $inputType . '"][starts-with(@name,"field[")][@value="' . $value . '"]';

            $this->assertStringContainsString('Februar', $html, $type);
            $this->assertSame(1, $x->query($input('1'))->length, $type);
            $this->assertSame(1, $x->query($input('2') . '[@checked]')->length, $type);   // the earlier choice is marked
            $this->assertSame(0, $x->query($input('1') . '[@checked]')->length, $type);
        }
    }


    // ---- text that is not plain ASCII -----------------------------------------------------------------

    public function test_values_with_accents_and_capital_a_ring_survive_the_split_into_lines(): void
    {
        // "Å" is the bytes C3 85, and 85 is also a line break to a pattern without the u flag
        $f = $this->save('select', ['option_values' => "Åbenrå\nÅlborg\r\nÆrø\nSønderjylland"]);

        $this->assertSame(['Åbenrå', 'Ålborg', 'Ærø', 'Sønderjylland'], $f->options->pluck('value')->all());
        $this->assertSame("Åbenrå\nÅlborg\nÆrø\nSønderjylland", $f->option_values);
        $this->assertNotFalse(json_encode($f->choicesForEditor()));
    }

    public function test_the_migration_keeps_values_with_accents_whole(): void
    {
        $f = new Fields();
        $f->name = 'places';
        $f->type = 'radio';
        $f->position = 1;
        $f->multiple = 0;
        $f->option_values = "Åbenrå\nÅlborg";
        $f->save();
        Field_options::query()->delete();

        (require base_path('database/migrations/2026_11_04_100000_create_field_options.php'))->seedFromOptionValues();

        $this->assertSame(['Åbenrå', 'Ålborg'], Field_options::where('field_id', $f->id)->orderBy('position')->pluck('value')->all());
    }

    public function test_a_text_field_has_no_options_to_edit_its_suggestions_stay_in_the_plain_box(): void
    {
        $f = $this->save('text', ['option_values' => "a suggestion\nanother"]);

        $this->assertSame([], $f->choicesForEditor());
        $this->assertSame("a suggestion\nanother", $f->option_values);
    }


    // ---- a language added (or removed) later -----------------------------------------------------------

    public function test_a_language_added_later_gets_its_own_column_after_the_others_and_shows_the_value_until_filled(): void
    {
        $f = $this->save('select', ['choices_json' => $this->choices([['value' => '1', 'labels' => ['en' => 'January', 'da' => 'Januar']]])]);

        $swedish = new Language();
        $swedish->iso2 = 'sv';
        $swedish->save();

        // the table: a column for every language, the new one last
        $x = $this->xpath('/fields');
        $headers = [];
        foreach ($x->query('//div[@id="choices_group"]//thead//th') as $th) {
            if (trim($th->textContent) !== '' && !str_starts_with(trim($th->textContent), 'Value')) {
                $headers[] = trim($th->textContent);
            }
        }
        $this->assertSame(['en', 'da', 'sv'], $headers);

        // what was written before is untouched, and the new language shows the value where it has no label yet
        $this->assertSame('January', $f->fresh()->choices('en')[0]['label']);
        $this->assertSame('Januar', $f->fresh()->choices('da')[0]['label']);
        $this->assertSame('1', $f->fresh()->choices('sv')[0]['label']);

        // saving can now give it labels
        $this->save('select', ['choices_json' => $this->choices([['value' => '1', 'labels' => ['en' => 'January', 'da' => 'Januar', 'sv' => 'Januari']]])], $f->id);
        $this->assertSame('Januari', $f->fresh()->choices('sv')[0]['label']);
    }

    public function test_the_import_format_lists_the_languages_in_the_order_they_were_added(): void
    {
        $swedish = new Language();
        $swedish->iso2 = 'sv';
        $swedish->save();

        // "value ; en ; da ; sv": a language added later is at the end, so lines written for the earlier ones keep their meaning
        $html = $this->actingAs($this->admin)->get('/fields')->getContent();

        $this->assertStringContainsString('<code>en ; da ; sv</code>', $html);
    }
    public function test_the_labels_of_a_language_that_no_longer_exists_are_dropped_when_the_field_is_saved(): void
    {
        $f = $this->save('select', ['choices_json' => $this->choices([['value' => '1', 'labels' => ['en' => 'January', 'da' => 'Januar']]])]);

        Language::where('iso2', 'da')->delete();
        $this->save('select', ['choices_json' => $this->choices([['value' => '1', 'labels' => ['en' => 'January', 'da' => 'Januar']]])], $f->id);

        $this->assertSame(['en' => 'January'], $f->fresh()->options[0]->labels);
    }
}
