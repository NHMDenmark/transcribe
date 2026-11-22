<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Language;
use App\Models\Project;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A checkboxes field with a separator saves the options ticked on a line as ONE value, joined with it and in the
 * order of the list ("1;3;5"); without one every tick is a line of its own, as it has always been.
 */
class CheckboxSeparatorTest extends TestCase
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

        $this->admin = $this->user('admin');
    }

    private function user(string $role = 'user'): User
    {
        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = ucfirst($role) . uniqid();
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function save(string $type, array $overrides = [], ?int $fieldId = null)
    {
        $data = [
            'field_id' => $fieldId ?? '', 'name' => 'decorations', 'type' => $type,
            'required' => '0', 'deducted' => '0', 'have_doubts' => '0', 'multiple' => '1', 'min_user_level' => '5',
            'choices_json' => json_encode(array_map(fn($v) => ['value' => (string) $v, 'labels' => []], [1, 2, 3, 4, 5])),
            'fields_name_en' => 'Decorations', 'fields_question_en' => 'Q', 'fields_text_en' => 'T', 'fields_helper_en' => 'H',
        ];

        return $this->actingAs($this->admin)->post('/field_edit', array_merge($data, $overrides));
    }

    private function field(string $separator = ';', string $type = 'checkboxes'): Fields
    {
        $this->save($type, ['choices_separator' => $separator])->assertRedirect('/fields')->assertSessionHas('success');

        return Fields::latest('id')->firstOrFail()->refresh();
    }

    // ---- the value ---------------------------------------------------------------------------------

    public function test_the_ticked_options_are_joined_in_the_order_of_the_list_not_of_the_clicks(): void
    {
        $f = $this->field();

        $this->assertSame('1;3;5', $f->joinChoices(['5', '1', '3']));
        $this->assertSame('1;3;5', $f->joinChoices(['3', '5', '1']));
        $this->assertSame('2', $f->joinChoices(['2']));
        $this->assertSame('', $f->joinChoices([]));
    }

    public function test_blanks_and_repeats_go_and_an_option_the_list_no_longer_has_is_kept_at_the_end(): void
    {
        $f = $this->field();

        $this->assertSame('1;4;gone', $f->joinChoices(['4', '', 'gone', '1', '4']));
    }

    public function test_a_saved_value_splits_back_into_its_options(): void
    {
        $f = $this->field(',');

        $this->assertSame(['1', '3', '5'], $f->splitChoices('1,3, 5'));
        $this->assertSame([], $f->splitChoices(''));
        $this->assertSame(['1', '3', '5'], $f->splitChoices($f->joinChoices(['5', '3', '1'])));
    }

    public function test_without_a_separator_nothing_is_joined(): void
    {
        $f = $this->field('');

        $this->assertNull($f->choices_separator);
        $this->assertFalse($f->joinsChoices());
        $this->assertSame(['x'], $f->splitChoices('x'));
    }

    // ---- saving the field -----------------------------------------------------------------------------

    public function test_a_checkboxes_field_keeps_its_separator_and_the_others_do_not(): void
    {
        $this->assertSame(';', $this->field(';')->choices_separator);

        foreach (['radio', 'select', 'text', 'textarea', 'date'] as $type) {
            $f = $this->field(';', $type);
            $this->assertNull($f->choices_separator, $type);
        }
    }

    public function test_the_ends_of_the_separator_are_ignored_and_the_field_does_not_split_into_parts(): void
    {
        $f = $this->field(' ; ');

        $this->assertSame(';', $f->choices_separator);
        // it is not the separator of the parts of a text: nothing here for the sync to split on
        $this->assertNull($f->explode_char);
        $this->assertNull($f->explode_fields);
    }

    public function test_a_separator_that_is_part_of_an_option_is_refused_and_nothing_is_saved(): void
    {
        $before = Fields::count();

        $this->save('checkboxes', [
            'choices_separator' => ';',
            'choices_json' => json_encode([['value' => 'a;b', 'labels' => []], ['value' => 'c', 'labels' => []]]),
        ])->assertRedirect('/fields')->assertSessionHas('error');

        $this->assertSame($before, Fields::count());

        // the same when an existing field is edited: it stays as it was
        $f = $this->field(';');
        $this->save('checkboxes', [
            'choices_separator' => ',',
            'choices_json' => json_encode([['value' => '1,5', 'labels' => []]]),
        ], $f->id)->assertSessionHas('error');
        $this->assertSame(';', $f->fresh()->choices_separator);
        $this->assertSame(5, $f->options()->count());
    }

    // ---- submitting -----------------------------------------------------------------------------------

    private function setUpSpecimen(Fields $field): Project
    {
        $project = new Project();
        $project->name = 'P';
        $project->user_id = 0;
        $project->active = 1;
        $project->field_view_default = 'all';
        $project->save();
        $project->fields()->attach($field->id);
        Specimen::create(['specimen_id' => '999222', 'project_id' => $project->id, 'images' => 'a.jpg', 'min_user_level' => 5]);

        return $project;
    }

    private function submit(User $user, Project $project, Fields $field, array $lines, array $extra = [])
    {
        return $this->actingAs($user)->post('/store_user_response', array_merge([
            'project_id' => $project->id, 'specimen_id' => '999222', 'field' => [$field->id => $lines],
        ], $extra));
    }

    private function rows(Fields $field, User $user): array
    {
        return Collaborative_fields::where('field_id', $field->id)->where('user_id', (string) $user->id)->where('status', '>', 0)
            ->orderBy('position')->get()->map(fn($r) => [$r->position, $r->value])->all();
    }

    public function test_with_a_separator_each_line_is_one_value(): void
    {
        $f = $this->field(';');
        $project = $this->setUpSpecimen($f);
        $volunteer = $this->user();

        // two lines (the field allows several): the ticks of each, in the order they were clicked
        $this->submit($volunteer, $project, $f, [0 => ['5', '1', '3'], 1 => ['2']]);

        $this->assertSame([[1, '1;3;5'], [2, '2']], $this->rows($f, $volunteer));
    }

    public function test_without_a_separator_every_tick_is_a_line_of_its_own_as_before(): void
    {
        $f = $this->field('');
        $project = $this->setUpSpecimen($f);
        $volunteer = $this->user();

        $this->submit($volunteer, $project, $f, ['5', '1', '3']);

        $this->assertSame([[1, '5'], [2, '1'], [3, '3']], $this->rows($f, $volunteer));
    }

    public function test_two_volunteers_who_tick_the_same_options_in_a_different_order_agree(): void
    {
        $f = $this->field(';');
        $project = $this->setUpSpecimen($f);
        $ann = $this->user();
        $bob = $this->user();

        $this->submit($ann, $project, $f, [0 => ['1', '2', '3']]);
        $response = $this->submit($bob, $project, $f, [0 => ['3', '2', '1']]);

        $this->assertSame($this->rows($f, $ann)[0][1], $this->rows($f, $bob)[0][1]);
        // equal values: no "different values" page for the second one
        $response->assertSessionMissing('different_values');
    }

    public function test_the_doubt_and_deducted_boxes_of_a_joined_line_stay_with_that_line(): void
    {
        $f = $this->field(';');
        $project = $this->setUpSpecimen($f);
        $volunteer = $this->user();

        $this->submit($volunteer, $project, $f, [0 => ['1'], 1 => ['2', '3']], ['doubt' => [$f->id => [1 => 1]], 'deducted' => [$f->id => [0 => 1]]]);

        $rows = Collaborative_fields::where('field_id', $f->id)->orderBy('position')->get();
        $this->assertSame(['1', '2;3'], $rows->pluck('value')->all());
        $this->assertSame([0, 1], [(int) $rows[0]->doubt, (int) $rows[0]->deducted]);   // the first line: deducted
        $this->assertSame([1, 0], [(int) $rows[1]->doubt, (int) $rows[1]->deducted]);   // the second: in doubt
    }

    // ---- the transcription form -----------------------------------------------------------------------

    private function doc(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new \DOMXPath($doc);
    }

    private function formFor(Fields $field, User $user, Project $project): \DOMXPath
    {
        return $this->doc($this->actingAs($user)->get('/projects/' . $project->id . '/999222')->assertOk()->getContent());
    }

    public function test_the_form_groups_the_ticks_of_a_joined_line_and_marks_what_was_saved(): void
    {
        $f = $this->field(';');
        $project = $this->setUpSpecimen($f);
        $volunteer = $this->user();
        $this->submit($volunteer, $project, $f, [0 => ['1', '3']]);

        $x = $this->formFor($f, $volunteer, $project);
        $box = fn(string $value) => '//input[@type="checkbox"][@value="' . $value . '"][starts-with(@name,"field[' . $f->id . ']")]';

        // one array per line: field[id][0][]
        $this->assertSame('field[' . $f->id . '][0][]', $x->query($box('1'))->item(0)->getAttribute('name'));
        foreach (['1', '3'] as $ticked) {
            $this->assertSame(1, $x->query($box($ticked) . '[@checked]')->length, $ticked);
        }
        foreach (['2', '4', '5'] as $notTicked) {
            $this->assertSame(0, $x->query($box($notTicked) . '[@checked]')->length, $notTicked);
        }
    }

    public function test_without_a_separator_the_form_posts_the_flat_array_as_before(): void
    {
        $f = $this->field('');
        $project = $this->setUpSpecimen($f);

        $x = $this->formFor($f, $this->user(), $project);

        $this->assertSame('field[' . $f->id . '][]', $x->query('//input[@type="checkbox"][@value="1"][starts-with(@name,"field[' . $f->id . ']")]')->item(0)->getAttribute('name'));
    }

    // ---- the field form --------------------------------------------------------------------------------

    public function test_the_field_form_has_the_separator_only_for_checkboxes(): void
    {
        $f = $this->field(';');

        $html = $this->actingAs($this->admin)->get('/fields')->assertOk()->getContent();
        $x = $this->doc($html);

        $group = $x->query('//div[@id="choices_separator_div"]')->item(0);
        $this->assertSame('checkboxes', $group->getAttribute('data-types'));
        $this->assertSame(1, $x->query('//div[@id="choices_separator_div"]//input[@name="choices_separator"]')->length);
        // the edit button carries it
        $this->assertSame(';', $x->query('//button[@data-field_id="' . $f->id . '"][@data-choices]')->item(0)->getAttribute('data-choices_separator'));
        // and the script refuses a separator that is inside an option
        $this->assertStringContainsString('separatorInOption', $html);
    }
}
