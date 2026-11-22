<?php

namespace Tests\Feature;

use App\Models\Fields;
use App\Models\Language;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListSearchTest extends TestCase
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

        $this->admin = $this->user('Admin person', 'admin');
    }

    private function user(string $name, string $role = 'user'): User
    {
        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = $name;
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function field(string $name, int $position, ?int $parent = null): Fields
    {
        $f = new Fields();
        $f->name = $name;
        $f->type = 'text';
        $f->position = $position;
        $f->parent = $parent ?? 0;
        $f->min_user_level = 5;
        $f->autocomplete = 0;
        $f->save();

        return $f;
    }

    private function xpath(string $url): \DOMXPath
    {
        $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new \DOMXPath($doc);
    }

    // ---- users ---------------------------------------------------------------------------------

    public function test_the_users_list_is_alphabetical_whatever_the_case(): void
    {
        $this->user('zed');
        $this->user('Bernard');
        $this->user('anna');

        $x = $this->xpath('/users');
        $names = [];
        // the name is the cell's own text; the avatar in front of it has letters of its own
        foreach ($x->query('//table[@id="example"]/tbody/tr/td[3]/text()[normalize-space()]') as $text) {
            $names[] = trim($text->textContent);
        }

        $this->assertSame(['Admin person', 'anna', 'Bernard', 'zed'], $names);
    }

    public function test_the_users_list_has_a_search_box_and_sortable_columns(): void
    {
        $x = $this->xpath('/users');

        $this->assertSame(1, $x->query('//div[@id="tools-example"]//input[contains(@class,"table-search")]')->length);

        $types = [];
        foreach ($x->query('//table[@id="example"]/thead/tr/th[@data-sort]') as $th) {
            $types[trim($th->textContent)] = $th->getAttribute('data-sort');
        }
        $this->assertSame(8, count($types));
        $this->assertContains('number', $types);
        $this->assertContains('text', $types);
        // the column with the buttons is not sortable
        $this->assertSame(1, $x->query('//table[@id="example"]/thead/tr/th[not(@data-sort)]')->length);
    }

    // ---- fields --------------------------------------------------------------------------------

    public function test_the_fields_list_keeps_its_own_order_and_offers_search_and_an_a_z_view(): void
    {
        $this->field('Zebra field', 1);
        $this->field('Apple field', 2);

        $x = $this->xpath('/fields');

        // by position, not alphabetical: that is the order of the forms
        $names = [];
        foreach ($x->query('//table[@id="fields-table"]/tbody/tr[starts-with(@id,"item-")]/td[2]') as $td) {
            $names[] = trim($td->textContent);
        }
        $this->assertSame(['Zebra field', 'Apple field'], $names);

        $this->assertSame(1, $x->query('//div[@id="tools-fields-table"]//input[contains(@class,"table-search")]')->length);
        $this->assertSame(1, $x->query('//div[@id="tools-fields-table"]//button[contains(@class,"table-az")]')->length);
    }

    // ---- the fields of a project ---------------------------------------------------------------

    public function test_the_project_fields_picker_has_a_search_box_over_fields_and_their_sub_fields(): void
    {
        $project = new Project();
        $project->name = 'P';
        $project->user_id = $this->admin->id;
        $project->active = 1;
        $project->field_view_default = 'all';
        $project->save();

        $parent = $this->field('Locality', 1);
        $this->field('Coordinates', 2, $parent->id);
        $this->field('Collector', 3);

        $x = $this->xpath('/projects/' . $project->id . '/fields');

        $this->assertSame(1, $x->query('//input[@id="field-search"]')->length);
        $this->assertSame(2, $x->query('//div[contains(@class,"field-group")]')->length);
        $this->assertSame(1, $x->query('//div[contains(@class,"field-group")]//div[contains(@class,"field-sub")]//input[@data-field-name="Coordinates"]')->length);
        // every field still has its checkbox inside the form, so hiding some never changes what is submitted
        $this->assertSame(3, $x->query('//form[@id="update_fields"]//input[@name="field[]"]')->length);
    }


    // ---- dragging the fields into another order -------------------------------------------------

    public function test_dropping_the_fields_in_a_new_order_saves_their_positions(): void
    {
        $a = $this->field('A field', 1);
        $b = $this->field('B field', 2);
        $c = $this->field('C field', 3);

        // what the page sends: the rows in the order they now are
        $payload = "item[]={$c->id}&item[]={$a->id}&item[]={$b->id}";
        $this->actingAs($this->admin)->post('/fields_sort', ['id' => $payload])->assertOk()->assertJson(['message' => 'Fields were sorted']);

        $this->assertSame(['C field', 'A field', 'B field'], Fields::orderBy('position')->pluck('name')->all());
        $this->assertSame(0, (int) $c->fresh()->position);
    }

    public function test_an_unknown_field_in_the_order_is_skipped_and_nothing_breaks(): void
    {
        $a = $this->field('A field', 1);
        $b = $this->field('B field', 2);

        $this->actingAs($this->admin)->post('/fields_sort', ['id' => "item[]={$b->id}&item[]=99999&item[]={$a->id}"])->assertOk();
        $this->actingAs($this->admin)->post('/fields_sort', ['id' => ''])->assertOk();

        $this->assertSame(['B field', 'A field'], Fields::orderBy('position')->pluck('name')->all());
    }

    public function test_only_those_who_manage_fields_can_change_their_order(): void
    {
        $a = $this->field('A field', 1);
        $b = $this->field('B field', 2);

        foreach (['user', 'project_manager'] as $role) {
            $this->actingAs($this->user('Someone', $role))->post('/fields_sort', ['id' => "item[]={$b->id}&item[]={$a->id}"])->assertForbidden();
        }

        $this->assertSame(['A field', 'B field'], Fields::orderBy('position')->pluck('name')->all());
    }

    public function test_the_fields_page_has_the_handle_and_the_script_that_saves_and_locks_the_order(): void
    {
        $this->field('A field', 1);

        $html = $this->actingAs($this->admin)->get('/fields')->assertOk()->getContent();
        $x = $this->xpath('/fields');

        // each row has its handle as the first cell, and the id the endpoint reads
        $this->assertSame(1, $x->query('//table[@id="fields-table"]/tbody/tr[starts-with(@id,"item-")]/td[1][.//i[contains(@class,"bi-grip-vertical")]]')->length);
        $this->assertStringContainsString("sortable({", $html);
        $this->assertStringContainsString('fields_sort', $html);
        // the A-Z view announces itself, and the list switches dragging off on it
        $this->assertStringContainsString('tabletools:order', $html);
        $this->assertStringContainsString("'disabled', locked", $html);
    }
}
