<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Validate_fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColumnPickerTest extends TestCase
{
    use RefreshDatabase;

    private Fields $field;
    private Project $project;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->field = new Fields();
        $this->field->name = 'locality';
        $this->field->type = 'text';
        $this->field->position = 1;
        $this->field->min_user_level = 5;
        $this->field->autocomplete = 0;
        $this->field->save();

        $this->project = new Project();
        $this->project->name = 'Oak Moth project';
        $this->project->user_id = 1;
        $this->project->active = 1;
        $this->project->field_view_default = 'all';
        $this->project->save();

        $c = new Collaborative_fields();
        $c->value = 'Boserup';
        $c->position = 0;
        $c->specimen_id = '912345';
        $c->field_id = $this->field->id;
        $c->user_id = (string) $this->admin->id;
        $c->project_id = $this->project->id;
        $c->status = 1;
        $c->points = 20;
        $c->remarks = 'a remark';
        $c->save();

        $v = new Validate_fields();
        $v->value = 'Boserup';
        $v->position = 0;
        $v->specimen_id = '912345';
        $v->field_id = $this->field->id;
        $v->user_id = 1;
        $v->project_id = $this->project->id;
        $v->status = 2;
        $v->save();
    }

    private function xpath(string $url): \DOMXPath
    {
        $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new \DOMXPath($doc);
    }

    /** @return array<string, bool> column key => shown, from the header row */
    private function headerColumns(\DOMXPath $x): array
    {
        $out = [];
        foreach ($x->query('//table[@id="example"]/thead/tr/th[@data-col]') as $th) {
            $out[$th->getAttribute('data-col')] = !str_contains(' ' . $th->getAttribute('class') . ' ', ' d-none ');
        }

        return $out;
    }

    public function test_the_submissions_table_has_a_project_column_with_the_project_name_as_a_link(): void
    {
        $x = $this->xpath('/all_submissions');

        $this->assertTrue($this->headerColumns($x)['project']);
        $link = $x->query('//table[@id="example"]/tbody/tr/td[@data-col="project"]/a')->item(0);
        $this->assertSame('Oak Moth project', trim($link->textContent));
        $this->assertSame(url('projects/' . $this->project->id), $link->getAttribute('href'));
    }

    public function test_the_validated_table_has_a_project_column_too(): void
    {
        $x = $this->xpath('/validated');

        $this->assertTrue($this->headerColumns($x)['project']);
        $this->assertSame('Oak Moth project', trim($x->query('//table[@id="example"]/tbody/tr/td[@data-col="project"]/a')->item(0)->textContent));
    }

    public function test_a_row_without_a_project_shows_a_dash(): void
    {
        $row = Collaborative_fields::first();
        $row->project_id = null;
        $row->save();

        $cell = $this->xpath('/all_submissions')->query('//table[@id="example"]/tbody/tr/td[@data-col="project"]')->item(0);

        $this->assertSame('—', trim($cell->textContent));
        $this->assertSame(0, (new \DOMXPath($cell->ownerDocument))->query('.//a', $cell)->length);
    }

    public function test_every_header_has_its_cells_and_extra_columns_are_there_but_hidden_by_default(): void
    {
        $x = $this->xpath('/all_submissions');
        $columns = $this->headerColumns($x);

        // everything that can be shown, among them the ones that start hidden
        foreach (['id', 'specimen', 'project', 'field', 'position', 'value', 'status', 'doubt', 'deducted', 'points', 'created_at', 'updated_at', 'displayed_at', 'time_spent', 'user', 'remarks'] as $key) {
            $this->assertArrayHasKey($key, $columns, $key);
            $this->assertSame(1, $x->query('//table[@id="example"]/tbody/tr[1]/td[@data-col="' . $key . '"]')->length, $key);
        }
        $this->assertFalse($columns['displayed_at']);
        $this->assertFalse($columns['remarks']);
        $this->assertTrue($columns['value']);

        // a hidden column is hidden in its cells too, with no script needed
        $this->assertStringContainsString('d-none', $x->query('//table[@id="example"]/tbody/tr[1]/td[@data-col="remarks"]')->item(0)->getAttribute('class'));
        $this->assertSame('a remark', trim($x->query('//table[@id="example"]/tbody/tr[1]/td[@data-col="remarks"]')->item(0)->textContent));
    }

    public function test_the_picker_offers_a_checkbox_per_column_ticked_as_the_table_starts(): void
    {
        $x = $this->xpath('/all_submissions');
        $columns = $this->headerColumns($x);

        foreach ($columns as $key => $shown) {
            $box = $x->query('//*[contains(@class,"column-picker")]//input[@data-col="' . $key . '"]')->item(0);
            $this->assertNotNull($box, $key);
            $this->assertSame($shown, $box->hasAttribute('checked'), $key);
        }
        $this->assertSame(1, $x->query('//*[contains(@class,"column-reset")]')->length);
    }

    public function test_the_validated_picker_keeps_the_sync_column_out_of_the_choices(): void
    {
        $x = $this->xpath('/validated');

        $this->assertSame(0, $x->query('//*[contains(@class,"column-picker")]//input[@data-col="sync"]')->length);
        // and the Sync header has no data-col, so hiding columns can never hide it
        $this->assertSame(1, $x->query('//table[@id="example"]/thead/tr/th[not(@data-col)][contains(.,"Sync")]')->length);
        $this->assertGreaterThan(0, $x->query('//table[@id="example"]/tbody/tr[1]/td[not(@data-col)]')->length);
    }

    public function test_each_list_remembers_its_own_choice(): void
    {
        $this->actingAs($this->admin);

        $all = $this->get('/all_submissions')->getContent();
        $validated = $this->get('/validated')->getContent();
        $mine = $this->get('/dashboard')->getContent();

        $this->assertStringContainsString('"columns.submissions.all"', $all);
        $this->assertStringContainsString('"columns.validated"', $validated);
        $this->assertStringContainsString('"columns.submissions.mine"', $mine);
    }

    public function test_a_long_value_wraps_in_its_column_instead_of_widening_the_table(): void
    {
        foreach (['/all_submissions', '/validated'] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('#example td[data-col="value"]', $html, $url);
            $this->assertStringContainsString('max-width: 40ch', $html, $url);
            $this->assertStringContainsString('overflow-wrap: anywhere', $html, $url);
        }
    }

    public function test_the_tables_use_the_whole_width_of_a_wide_screen(): void
    {
        foreach (['/all_submissions', '/validated'] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            // a fixed-width .container stops at 1320px: on a widescreen the table would scroll sideways with room to spare
            $this->assertSame(2, substr_count($html, '<div class="container-fluid px-lg-4">'), $url);   // the heading and the table
        }
    }

    public function test_the_project_can_be_sorted_on(): void
    {
        $this->xpath('/all_submissions?sort=project_id&direction=asc');
        $this->xpath('/validated?sort=project_id&direction=desc');

        $links = $this->xpath('/all_submissions')->query('//table[@id="example"]/thead//th[@data-col="project"]/a[contains(@href,"sort=project_id")]');
        $this->assertSame(1, $links->length);
    }
}
