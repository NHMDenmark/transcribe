<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Validate_fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class IgnoreAndProjectFilterTest extends TestCase
{
    use RefreshDatabase;

    private Fields $field;
    private Project $alpha;
    private Project $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $this->field = new Fields();
        $this->field->name = 'locality';
        $this->field->type = 'text';
        $this->field->position = 1;
        $this->field->min_user_level = 5;
        $this->field->autocomplete = 0;
        $this->field->save();

        $this->alpha = $this->project('Alpha project', 1);
        $this->beta = $this->project('Beta project', 1);
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

    private function project(string $name, int $owner): Project
    {
        $p = new Project();
        $p->name = $name;
        $p->user_id = $owner;
        $p->active = 1;
        $p->field_view_default = 'all';
        $p->save();

        return $p;
    }

    private function validated(string $value, ?Project $project, int $status = 1): Validate_fields
    {
        $v = new Validate_fields();
        $v->value = $value;
        $v->position = 0;
        $v->specimen_id = '9' . random_int(10000, 99999);
        $v->field_id = $this->field->id;
        $v->user_id = 1;
        $v->project_id = $project?->id;
        $v->status = $status;
        $v->save();

        return $v;
    }

    private function submission(string $value, ?Project $project, int $userId): Collaborative_fields
    {
        $c = new Collaborative_fields();
        $c->value = $value;
        $c->position = 0;
        $c->specimen_id = '9' . random_int(10000, 99999);
        $c->field_id = $this->field->id;
        $c->user_id = (string) $userId;
        $c->project_id = $project?->id;
        $c->status = 1;
        $c->points = 20;
        $c->save();

        return $c;
    }

    // ---- ignoring a validated value ----------------------------------------------------------

    public function test_a_waiting_or_failed_value_can_be_set_aside_and_is_then_never_queued_for_the_sync(): void
    {
        $admin = $this->user('admin');
        $waiting = $this->validated('Waiting', $this->alpha, 1);
        $failed = $this->validated('Failed', $this->alpha, 3);

        $this->actingAs($admin)->post('/validated/' . $waiting->id . '/ignore')->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->post('/validated/' . $failed->id . '/ignore')->assertRedirect();

        $this->assertSame(7, (int) $waiting->fresh()->status);
        $this->assertSame(7, (int) $failed->fresh()->status);
        // the sync only ever takes records waiting (1) or approved to overwrite (6)
        $this->assertSame(0, Validate_fields::whereIn('status', [1, 6])->count());
    }

    public function test_what_is_already_in_specify_or_being_resolved_cannot_be_set_aside(): void
    {
        $admin = $this->user('admin');

        foreach ([0, 2, 4, 5, 6, 7] as $status) {
            $row = $this->validated('Status ' . $status, $this->alpha, $status);
            $this->actingAs($admin)->post('/validated/' . $row->id . '/ignore')->assertStatus(409);
            $this->assertSame($status, (int) $row->fresh()->status);
        }
    }

    public function test_only_root_and_admin_may_set_a_value_aside(): void
    {
        $row = $this->validated('Waiting', $this->alpha, 1);

        foreach (['user', 'project_manager'] as $role) {
            $this->actingAs($this->user($role))->post('/validated/' . $row->id . '/ignore')->assertForbidden();
        }
        Auth::logout();
        $this->post('/validated/' . $row->id . '/ignore')->assertRedirect(route('login'));

        $this->assertSame(1, (int) $row->fresh()->status);
    }

    public function test_the_list_offers_the_button_only_on_waiting_and_failed_rows_and_can_filter_the_set_aside_ones(): void
    {
        $this->validated('Row waiting', $this->alpha, 1);
        $this->validated('Row failed', $this->alpha, 3);
        $this->validated('Row sent', $this->alpha, 2);
        $this->validated('Row aside', $this->alpha, 7);

        $page = $this->actingAs($this->user('admin'))->get('/validated');
        $forms = substr_count($page->getContent(), '/ignore"');
        $this->assertSame(2, $forms);   // waiting and failed

        $this->actingAs($this->user('admin'))->get('/validated?status=7')->assertOk()
            ->assertSee('Row aside')->assertDontSee('Row waiting')->assertDontSee('Row sent')
            ->assertSee('Ignored: not sent to Specify');
    }

    public function test_the_action_buttons_of_a_row_have_space_between_them(): void
    {
        $this->validated('Row waiting', $this->alpha, 1);

        $html = $this->actingAs($this->user('admin'))->get('/validated')->getContent();

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new \DOMXPath($doc);

        // the Send / No-mapping button and the Ignore button share one flex box with a gap
        $this->assertSame(1, $xpath->query('//td/div[contains(@class,"d-flex")][contains(@class,"gap-1")][.//form[contains(@action,"/ignore")]]')->length);
    }

    // ---- filtering by project ----------------------------------------------------------------

    public function test_the_validated_list_can_be_filtered_by_project(): void
    {
        $this->validated('Value in alpha', $this->alpha);
        $this->validated('Value in beta', $this->beta);
        $this->validated('Value in no project', null);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/validated')->assertSee('Value in alpha')->assertSee('Value in beta')->assertSee('Value in no project');
        $this->actingAs($admin)->get('/validated?project_id=' . $this->alpha->id)->assertSee('Value in alpha')->assertDontSee('Value in beta')->assertDontSee('Value in no project');
        $this->actingAs($admin)->get('/validated?project_id=none')->assertSee('Value in no project')->assertDontSee('Value in alpha');
    }

    public function test_the_filter_offers_the_projects(): void
    {
        $html = $this->actingAs($this->user('admin'))->get('/validated')->getContent();

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new \DOMXPath($doc);

        $this->assertSame(1, $xpath->query('//select[@name="project_id"]/option[@value="' . $this->alpha->id . '"]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="project_id"]/option[@value="' . $this->beta->id . '"]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="project_id"]/option[@value="none"]')->length);
    }

    public function test_a_project_manager_only_gets_their_own_projects_in_the_filter_and_never_the_others_rows(): void
    {
        $manager = $this->user('project_manager');
        $mine = $this->project('Manager project', $manager->id);
        $this->validated('Mine row', $mine, 4);
        $this->validated('Other row', $this->alpha, 4);

        $page = $this->actingAs($manager)->get('/validated')->assertOk()->assertSee('Manager project')->assertDontSee('Alpha project')->assertSee('Mine row')->assertDontSee('Other row');

        // asking for somebody else's project does not open it
        $this->actingAs($manager)->get('/validated?project_id=' . $this->alpha->id)->assertOk()->assertDontSee('Other row');
    }

    public function test_the_submissions_lists_can_be_filtered_by_project_too(): void
    {
        $admin = $this->user('admin');
        $volunteer = $this->user();
        $this->submission('Sub in alpha', $this->alpha, $volunteer->id);
        $this->submission('Sub in beta', $this->beta, $volunteer->id);

        // every submission (admin)
        $this->actingAs($admin)->get('/all_submissions?project_id=' . $this->alpha->id)->assertOk()
            ->assertSee('Sub in alpha')->assertDontSee('Sub in beta')->assertSee('Beta project');

        // one's own submissions: the filter only offers the projects they worked on
        $this->actingAs($volunteer)->get('/dashboard?project_id=' . $this->beta->id)->assertOk()
            ->assertSee('Sub in beta')->assertDontSee('Sub in alpha');
    }
}
