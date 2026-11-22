<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Validate_fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, int $level = 5): User
    {
        $cat = User_categories::find($level) ?? new User_categories();
        $cat->id = $level;
        $cat->category = 'level ' . $level;
        $cat->points = $level * 10;
        $cat->save();

        $user = new User();
        $user->category = $level;
        $user->role = $role;
        $user->name = ucfirst($role) . uniqid();
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->field_view = 'all';
        $user->save();

        return $user;
    }

    private function project(User $owner, int $active = 1): Project
    {
        $project = new Project();
        $project->name = 'P' . uniqid();
        $project->user_id = $owner->id;
        $project->active = $active;
        $project->save();

        return $project;
    }

    public function test_a_regular_user_is_kept_out_of_every_admin_area(): void
    {
        $user = $this->user('user');

        foreach (['/users', '/languages', '/fields', '/user_categories', '/all_submissions', '/all_stats', '/validate/manual'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
        $this->actingAs($user)->post('/user_edit', ['name' => 'x', 'email' => 'x@x.test', 'category' => 5, 'user_id' => $user->id, 'role' => 'root'])->assertForbidden();
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_an_admin_runs_the_day_to_day_but_not_the_instance(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/all_submissions')->assertOk();
        $this->actingAs($admin)->get('/settings')->assertOk();               // the page opens for the access section...
        $this->actingAs($admin)->post('/settings', [])->assertForbidden();    // ...but the branding stays root's
        $this->actingAs($admin)->get('/languages')->assertForbidden();
    }

    public function test_root_has_the_instance_too(): void
    {
        $root = $this->user('root');

        $this->actingAs($root)->get('/settings')->assertOk();
        $this->actingAs($root)->get('/languages')->assertOk();
    }

    public function test_only_root_can_change_a_role_and_an_admin_can_still_set_the_level(): void
    {
        $target = $this->user('user', 5);
        $this->user('user', 6);   // makes level 6 exist

        $payload = fn(string $role) => ['name' => 'T', 'email' => $target->email, 'category' => 6, 'user_id' => $target->id, 'role' => $role];

        $this->actingAs($this->user('admin'))->post('/user_edit', $payload('root'));
        $this->assertSame('user', $target->fresh()->role);          // an admin cannot hand out roles...
        $this->assertSame(6, (int) $target->fresh()->category);     // ...but can set the level

        $this->actingAs($this->user('root'))->post('/user_edit', $payload('project_manager'));
        $this->assertSame('project_manager', $target->fresh()->role);

        $this->actingAs($this->user('root'))->post('/user_edit', $payload('nonsense'));
        $this->assertSame('project_manager', $target->fresh()->role);   // only known roles
    }

    public function test_a_project_manager_works_on_their_own_and_given_projects_only(): void
    {
        $pm = $this->user('project_manager');
        $mine = $this->project($pm);
        $given = $this->project($this->user('admin'));
        $given->managers()->attach($pm->id);
        $other = $this->project($this->user('admin'));

        $this->actingAs($pm)->get("/projects/{$mine->id}/upload")->assertOk();
        $this->actingAs($pm)->get("/projects/{$given->id}/upload")->assertOk();
        $this->actingAs($pm)->get("/projects/{$other->id}/upload")->assertForbidden();
        $this->actingAs($pm)->get("/projects/{$other->id}/specimens")->assertForbidden();
        $this->actingAs($pm)->get("/projects/{$other->id}/fields")->assertRedirect('/projects');
    }

    public function test_a_project_manager_cannot_wipe_someone_elses_specimens(): void
    {
        $pm = $this->user('project_manager');
        $other = $this->project($this->user('admin'));
        Specimen::create(['specimen_id' => '1', 'project_id' => $other->id, 'images' => 'a.jpg']);

        // this used to delete every specimen of the project *before* looking at permissions
        $this->actingAs($pm)->post('/update_specimens_submit', ['project_id' => $other->id, 'specimens' => '1']);

        $this->assertSame(1, Specimen::where('project_id', $other->id)->count());
    }

    public function test_creating_a_project_makes_the_creator_its_owner_and_later_edits_keep_it(): void
    {
        $pm = $this->user('project_manager');
        $admin = $this->user('admin');

        $this->actingAs($pm)->post('/projects', ['name' => 'Mine'])->assertRedirect('/projects');
        $project = Project::where('name', 'Mine')->firstOrFail();
        $this->assertSame($pm->id, (int) $project->user_id);

        // an admin renaming it must not take ownership away from the creator
        $this->actingAs($admin)->post('/projects', ['name' => 'Renamed', 'project_id' => $project->id]);
        $this->assertSame($pm->id, (int) $project->fresh()->user_id);
        $this->assertSame('Renamed', $project->fresh()->name);
    }

    public function test_a_regular_user_cannot_create_or_edit_projects(): void
    {
        $user = $this->user('user');
        $project = $this->project($this->user('admin'));

        $this->actingAs($user)->post('/projects', ['name' => 'Sneaky'])->assertForbidden();
        $this->actingAs($user)->post('/projects', ['name' => 'Hijack', 'project_id' => $project->id])->assertForbidden();
        $this->assertSame(0, Project::where('name', 'Sneaky')->count());
        $this->assertNotSame('Hijack', $project->fresh()->name);
    }

    public function test_the_managers_of_a_project_are_set_by_root_or_admin_only(): void
    {
        $admin = $this->user('admin');
        $pm = $this->user('project_manager');
        $notPm = $this->user('user');
        $project = $this->project($admin);

        $form = ['name' => $project->name, 'project_id' => $project->id, 'managers_present' => 1];
        $managers = fn() => $project->managers()->pluck('users.id')->all();

        $this->actingAs($admin)->post('/projects', $form + ['managers' => [$pm->id, $notPm->id]]);
        $this->assertSame([$pm->id], $managers());   // only users with the role

        $this->actingAs($pm)->post('/projects', $form + ['managers' => []]);
        $this->assertSame([$pm->id], $managers());   // a manager cannot reshuffle them
    }

    public function test_unticking_everyone_removes_the_managers_but_a_form_without_the_list_leaves_them(): void
    {
        $admin = $this->user('admin');
        $pm = $this->user('project_manager');
        $project = $this->project($admin);
        $project->managers()->attach($pm->id);
        $form = ['name' => $project->name, 'project_id' => $project->id];

        // a form that does not carry the list at all (no checkbox sent, no marker) changes nothing
        $this->actingAs($admin)->post('/projects', $form);
        $this->assertSame([$pm->id], $project->managers()->pluck('users.id')->all());

        // the real form with every box unticked: the marker is there, no managers[] is sent
        $this->actingAs($admin)->post('/projects', $form + ['managers_present' => 1]);
        $this->assertSame([], $project->managers()->pluck('users.id')->all());
    }

    public function test_the_project_form_lists_project_managers_as_ticked_boxes_and_explains_when_there_are_none(): void
    {
        $admin = $this->user('admin');
        $project = $this->project($admin);

        $this->actingAs($admin);
        $empty = view('projects.form', ['project' => $project->fresh(), 'api_schema' => []])->render();
        $this->assertStringContainsString('No user has the', $empty);

        $pm = $this->user('project_manager');
        $other = $this->user('project_manager');
        $project->managers()->attach($pm->id);

        $html = view('projects.form', ['project' => $project->fresh(), 'api_schema' => []])->render();
        $this->assertMatchesRegularExpression('/id="manager_' . $pm->id . '" value="' . $pm->id . '" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="manager_' . $other->id . '" value="' . $other->id . '" checked/', $html);
        $this->assertStringContainsString('name="managers_present"', $html);
    }

    public function test_conflicts_can_be_resolved_by_the_projects_manager_and_nobody_below(): void
    {
        $pm = $this->user('project_manager');
        $mine = $this->project($pm);
        $other = $this->project($this->user('admin'));

        $make = function (Project $project) {
            $r = new Validate_fields();
            $r->value = 'v';
            $r->position = 1;
            $r->specimen_id = '1';
            $r->field_id = 1;
            $r->user_id = 1;
            $r->project_id = $project->id;
            $r->status = 4;
            $r->save();

            return $r;
        };
        $ownConflict = $make($mine);
        $otherConflict = $make($other);

        $this->actingAs($pm)->post("/conflicts/{$ownConflict->id}/keep")->assertRedirect('/validated?status=4');
        $this->actingAs($pm)->post("/conflicts/{$otherConflict->id}/keep")->assertForbidden();
        $this->actingAs($this->user('user'))->post("/conflicts/{$otherConflict->id}/keep")->assertForbidden();
        $this->assertSame(5, (int) $ownConflict->fresh()->status);
        $this->assertSame(4, (int) $otherConflict->fresh()->status);
    }

    public function test_the_validated_list_shows_a_project_manager_only_their_projects(): void
    {
        $pm = $this->user('project_manager');
        $mine = $this->project($pm);
        $other = $this->project($this->user('admin'));

        foreach ([[$mine, 'MINE-RECORD'], [$other, 'OTHER-RECORD']] as [$project, $value]) {
            $r = new Validate_fields();
            $r->value = $value;
            $r->position = 1;
            $r->specimen_id = '1';
            $r->field_id = 1;
            $r->user_id = 1;
            $r->project_id = $project->id;
            $r->status = 4;
            $r->save();
        }

        $this->actingAs($pm)->get('/validated?status=4')->assertOk()->assertSee('MINE-RECORD')->assertDontSee('OTHER-RECORD');
        $this->actingAs($this->user('admin'))->get('/validated?status=4')->assertSee('MINE-RECORD')->assertSee('OTHER-RECORD');
        $this->actingAs($this->user('user'))->get('/validated?status=4')->assertForbidden();
    }

    public function test_the_project_list_hides_inactive_projects_from_everyone_but_those_who_manage_them(): void
    {
        $pm = $this->user('project_manager');
        $own = $this->project($pm, 0);
        $someoneElses = $this->project($this->user('admin'), 0);
        $this->user('user');

        $link = fn(Project $p) => 'projects/' . $p->id . '"';   // the "read more" link of a project box

        $this->actingAs($pm)->get('/projects')->assertOk()->assertSee($link($own), false)->assertDontSee($link($someoneElses), false);
        $this->actingAs($this->user('user'))->get('/projects')->assertDontSee($link($own), false);
        $this->actingAs($this->user('admin'))->get('/projects')->assertSee($link($own), false)->assertSee($link($someoneElses), false);
    }
}
