<?php

namespace Tests\Feature;

use App\Models\Fields;
use App\Models\Language;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The field form only offers what applies to the field's type, and saving clears what the type does not use.
 *
 *   explode (fields, separator, direction)  text, date
 *   autocomplete                            text, textarea
 *   list of options                         select, radio, checkboxes (the choices); text, textarea (suggestions)
 *   everything else (required, doubt, deducted, multiple, level, n.a. default) is for every type
 */
class FieldFormByTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $language = new Language();
        $language->iso2 = 'en';
        $language->save();
        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $this->admin = new User();
        $this->admin->category = 5;
        $this->admin->role = 'admin';
        $this->admin->name = 'Admin';
        $this->admin->email = uniqid() . '@example.test';
        $this->admin->password = bcrypt('password');
        $this->admin->email_verified_at = now();
        $this->admin->save();
    }

    /** What the form sends: every property, as it would be filled in for a text field with everything on. */
    private function save(string $type, array $overrides = [], ?int $fieldId = null): Fields
    {
        $data = [
            'field_id' => $fieldId ?? '',
            'name' => 'myField',
            'type' => $type,
            'explode_fields' => 'First|Second',
            'explode_char' => '|',
            'explode_save_direction' => 'reverse',
            'autocomplete' => '1',
            'option_values' => "one\ntwo",
            'required' => '1',
            'deducted' => '1',
            'have_doubts' => '1',
            'multiple' => '1',
            'min_user_level' => '5',
            'na_defaults' => 'n.a.||x',
            'fields_name_en' => 'Name', 'fields_question_en' => 'Q', 'fields_text_en' => 'T', 'fields_helper_en' => 'H',
        ];
        $this->actingAs($this->admin)->post('/field_edit', array_merge($data, $overrides))->assertRedirect('/fields');

        return Fields::latest('id')->firstOrFail()->refresh();
    }

    // ---- saving -------------------------------------------------------------------------------

    public function test_a_text_field_keeps_everything(): void
    {
        $f = $this->save('text');

        $this->assertSame('First|Second', $f->explode_fields);
        $this->assertSame('|', $f->explode_char);
        $this->assertSame('reverse', $f->explode_save_direction);
        $this->assertSame(1, (int) $f->autocomplete);
        $this->assertSame("one\ntwo", $f->option_values);
    }

    public function test_a_text_field_keeps_its_suggestions_when_the_autocomplete_is_off(): void
    {
        $f = $this->save('text', ['autocomplete' => '0']);

        $this->assertSame(0, (int) $f->autocomplete);
        $this->assertSame("one\ntwo", $f->option_values);   // hidden by the form, not thrown away
    }

    public function test_a_textarea_keeps_the_autocomplete_and_suggestions_but_has_no_explode(): void
    {
        $f = $this->save('textarea');

        $this->assertNull($f->explode_fields);
        $this->assertNull($f->explode_char);
        $this->assertNull($f->explode_save_direction);
        $this->assertSame(1, (int) $f->autocomplete);
        $this->assertSame("one\ntwo", $f->option_values);
    }

    public function test_a_date_field_has_no_autocomplete_or_options_but_may_have_parts(): void
    {
        $f = $this->save('date', ['explode_fields' => 'Year|Month|Day', 'explode_char' => '-']);

        $this->assertSame('Year|Month|Day', $f->explode_fields);
        $this->assertSame('-', $f->explode_char);
        $this->assertSame(0, (int) $f->autocomplete);
        $this->assertNull($f->option_values);
    }

    public function test_the_fields_that_choose_from_a_list_keep_the_list_and_nothing_else_of_that_kind(): void
    {
        foreach (['select', 'radio', 'checkboxes'] as $type) {
            $f = $this->save($type);

            $this->assertSame("one\ntwo", $f->option_values, $type);
            $this->assertNull($f->explode_fields, $type);
            $this->assertNull($f->explode_char, $type);
            $this->assertNull($f->explode_save_direction, $type);
            $this->assertSame(0, (int) $f->autocomplete, $type);
        }
    }

    public function test_what_is_for_every_type_is_kept_for_every_type(): void
    {
        foreach (['text', 'date', 'textarea', 'select', 'radio', 'checkboxes'] as $type) {
            $f = $this->save($type, ['required' => '0', 'deducted' => '0', 'have_doubts' => '0', 'multiple' => '1', 'min_user_level' => '5']);

            $this->assertSame(0, (int) $f->required, $type);
            $this->assertSame(0, (int) $f->deducted, $type);
            $this->assertSame(0, (int) $f->have_doubts, $type);
            $this->assertSame(1, (int) $f->multiple, $type);    // "add line" is used with checkboxes in production
            $this->assertSame('n.a.||x', $f->na_defaults, $type);
        }
    }

    public function test_a_field_of_a_type_the_form_does_not_offer_is_left_alone(): void
    {
        // a tree: its options are a JSON tree, and nothing here applies to it
        $f = $this->save('tree', ['option_values' => '[{"label":"A"}]']);

        $this->assertSame('tree', $f->type);
        $this->assertSame('[{"label":"A"}]', $f->option_values);
        $this->assertSame('First|Second', $f->explode_fields);
        $this->assertSame(1, (int) $f->autocomplete);
    }

    public function test_changing_the_type_of_an_existing_field_clears_what_the_new_type_does_not_use(): void
    {
        $text = $this->save('text');
        $this->assertSame('First|Second', $text->explode_fields);

        // the same field, now a radio (the form no longer sends the parts, which were hidden)
        $this->actingAs($this->admin)->post('/field_edit', [
            'field_id' => $text->id, 'name' => 'myField', 'type' => 'radio', 'option_values' => "yes\nno",
            'required' => '1', 'deducted' => '1', 'have_doubts' => '1', 'multiple' => '0', 'min_user_level' => '5',
            'fields_name_en' => 'Name', 'fields_question_en' => 'Q', 'fields_text_en' => 'T', 'fields_helper_en' => 'H',
        ])->assertRedirect('/fields');

        $radio = $text->fresh();
        $this->assertSame('radio', $radio->type);
        $this->assertNull($radio->explode_fields);
        $this->assertNull($radio->explode_char);
        $this->assertSame(0, (int) $radio->autocomplete);
        $this->assertSame("yes\nno", $radio->option_values);
    }

    // ---- the form -----------------------------------------------------------------------------

    private function xpath(): \DOMXPath
    {
        $html = $this->actingAs($this->admin)->get('/fields')->assertOk()->getContent();
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new \DOMXPath($doc);
    }

    public function test_the_form_has_one_list_of_options_and_the_groups_know_their_types(): void
    {
        $x = $this->xpath();
        $modal = '//div[@id="field_Modal"]';

        // one box, one id: no more copy per type
        $this->assertSame(1, $x->query($modal . '//textarea[@name="option_values"]')->length);
        $this->assertSame(1, $x->query('//*[@id="option_values"]')->length);
        $this->assertSame(0, $x->query($modal . '//*[contains(@class,"option-target")]')->length);
        // its two labels: choices, or suggestions
        $this->assertSame(1, $x->query($modal . '//*[@id="options_group"]//*[contains(@class,"options-label-choice")]')->length);
        $this->assertSame(1, $x->query($modal . '//*[@id="options_group"]//*[contains(@class,"options-label-suggest")]')->length);

        $types = fn(string $id) => $x->query($modal . '//*[@id="' . $id . '"]')->item(0)->getAttribute('data-types');
        $this->assertSame('text date', $types('explode_fields_div'));
        $this->assertSame('text date', $types('explode_char_div'));
        $this->assertSame('text date', $types('explode_save_direction_div'));
        $this->assertSame('text textarea', $types('autocomplete_div'));
    }

    public function test_what_is_for_every_type_has_no_type_restriction(): void
    {
        $x = $this->xpath();

        foreach (['required', 'deducted', 'have_doubts', 'multiple', 'min_user_level', 'na_defaults'] as $name) {
            $group = $x->query('//div[@id="field_Modal"]//*[@name="' . $name . '"]/ancestor::div[contains(@class,"form-group")][1]')->item(0);
            $this->assertNotNull($group, $name);
            $this->assertSame('', $group->getAttribute('data-types'), $name);
        }
    }

    public function test_the_form_script_applies_the_type_and_protects_a_type_it_does_not_offer(): void
    {
        $html = $this->actingAs($this->admin)->get('/fields')->assertOk()->getContent();

        $this->assertStringContainsString('function applyType()', $html);
        $this->assertStringContainsString("\$j('#type, #autocomplete').on('change', applyType);", $html);
        // a stored type that is not in the list is added to it, so editing cannot lose it
        $this->assertStringContainsString('storedType', $html);
        // a new field starts with the autocomplete on and direct order
        $this->assertStringContainsString("button.data('field_id') ? (button.data('autocomplete') || '0') : '1'", $html);
    }
}
