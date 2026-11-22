<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Project;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeesOtherSubmissionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $category, string $name, ?string $role = null): User
    {
        $cat = User_categories::find($category) ?? new User_categories();
        $cat->id = $category;
        $cat->category = 'c' . $category;
        $cat->points = $category === 1 ? 60 : 20;
        $cat->save();

        $user = new User();
        $user->category = $category;
        $user->role = $role ?? ($category <= 1 ? 'admin' : 'user'); // the old convention (level 0/1 = admin) only decides the default role here
        $user->name = $name;
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->field_view = 'all';
        $user->save();

        return $user;
    }

    private function submit(User $user, Project $project, Fields $field, string $value, int $points = 20, int $status = 1, int $position = 1): void
    {
        $row = new Collaborative_fields();
        $row->value = $value;
        $row->position = $position;
        $row->specimen_id = '999050';
        $row->field_id = $field->id;
        $row->user_id = $user->id;
        $row->points = $points;
        $row->status = $status;
        $row->project_id = $project->id;
        $row->save();
    }

    private function setUpField(): array
    {
        $project = new Project();
        $project->name = 'P';
        $project->user_id = 1;
        $project->active = 1;
        $project->field_view_default = 'all';
        $project->save();

        $field = new Fields();
        $field->name = 'locality';
        $field->type = 'text';
        $field->position = 1;
        $field->min_user_level = 5;
        $field->save();
        $project->fields()->attach($field->id);

        Specimen::create(['specimen_id' => '999050', 'project_id' => $project->id, 'images' => 'a.jpg']);

        return [$project, $field];
    }

    public function test_an_admin_sees_the_other_users_values_grouped_with_their_points(): void
    {
        [$project, $field] = $this->setUpField();
        $admin = $this->user(1, 'Admin');
        $ann = $this->user(5, 'Ann');
        $bob = $this->user(6, 'Bob');
        $cid = $this->user(7, 'Cid');

        $this->submit($ann, $project, $field, 'Boserup');
        $this->submit($bob, $project, $field, 'Boserup');
        $this->submit($cid, $project, $field, 'Boserup Skov', 30);

        $response = $this->actingAs($admin)->get("/projects/{$project->id}/999050");

        $response->assertOk()
            ->assertSee('Use this value')
            ->assertSee('Ann · 20 points', false)    // identical values share one entry...
            ->assertSee('Bob · 20 points', false)    // ...listing each person with their own points
            ->assertDontSee('40 points', false)      // (no total)
            ->assertSee('Cid')
            ->assertSee('Boserup Skov');
        $this->assertSame(2, substr_count($response->getContent(), 'class="btn btn-sm btn-outline-primary use-value'));
    }

    public function test_an_ordinary_user_does_not_see_other_users_values(): void
    {
        [$project, $field] = $this->setUpField();
        $ann = $this->user(5, 'Ann');
        // a name that no random token on the page (the CSRF token, say) could contain by chance
        $bob = $this->user(6, 'Bobbington Quillfeather');
        $this->submit($bob, $project, $field, 'Boserup');

        $this->actingAs($ann)->get("/projects/{$project->id}/999050")
            ->assertOk()
            ->assertDontSee('Use this value')
            ->assertDontSee('Bobbington Quillfeather');
    }

    public function test_the_admins_own_submission_and_already_decided_ones_are_left_out(): void
    {
        [$project, $field] = $this->setUpField();
        $admin = $this->user(1, 'Admin');
        $ann = $this->user(5, 'Ann');

        $this->submit($admin, $project, $field, 'Mine');
        $this->submit($ann, $project, $field, 'Accepted already', 20, 2);
        $this->submit($ann, $project, $field, 'Rejected already', 20, 3);

        $this->actingAs($admin)->get("/projects/{$project->id}/999050")
            ->assertOk()
            ->assertDontSee('Use this value');
    }

    public function test_submissions_made_under_another_project_are_not_mixed_in(): void
    {
        [$project, $field] = $this->setUpField();
        $other = new Project();
        $other->name = 'Other';
        $other->user_id = 1;
        $other->active = 1;
        $other->save();

        $admin = $this->user(1, 'Admin');
        $ann = $this->user(5, 'Ann');
        $this->submit($ann, $other, $field, 'Elsewhere');

        $this->actingAs($admin)->get("/projects/{$project->id}/999050")
            ->assertOk()
            ->assertDontSee('Elsewhere');
    }

    public function test_every_line_of_a_multi_value_submission_is_kept_together(): void
    {
        [$project, $field] = $this->setUpField();
        $admin = $this->user(1, 'Admin');
        $ann = $this->user(5, 'Ann');

        $this->submit($ann, $project, $field, 'Flemming', 20, 1, 1);
        $this->submit($ann, $project, $field, 'Vilhelmsen', 20, 1, 2);

        $response = $this->actingAs($admin)->get("/projects/{$project->id}/999050");

        $response->assertSee('Flemming')->assertSee('Vilhelmsen');
        $this->assertSame(1, substr_count($response->getContent(), 'class="btn btn-sm btn-outline-primary use-value'));
        $this->assertStringContainsString('&quot;position&quot;:2', $response->getContent());
    }
    public function test_a_project_manager_sees_the_panel_only_on_the_projects_they_manage(): void
    {
        [$project, $field] = $this->setUpField();
        $pm = $this->user(5, 'Pat', 'project_manager');
        $ann = $this->user(6, 'Ann');
        $this->submit($ann, $project, $field, 'Boserup');

        // not theirs: no panel
        $this->actingAs($pm)->get("/projects/{$project->id}/999050")->assertOk()->assertDontSee('Use this value');

        // once they manage it: panel
        $project->managers()->attach($pm->id);
        $this->actingAs($pm)->get("/projects/{$project->id}/999050")->assertOk()->assertSee('Use this value')->assertSee('Boserup');
    }
}
