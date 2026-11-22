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

class OthersPanelAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private function user(string $role, string $name): User
    {
        $cat = User_categories::find(5) ?? new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = $name;
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->field_view = 'all';
        $user->save();

        return $user;
    }

    private function setUpProject(?string $audience): void
    {
        $this->project = new Project();
        $this->project->name = 'P';
        $this->project->user_id = 0;
        $this->project->active = 1;
        $this->project->field_view_default = 'all';
        if ($audience !== null) {
            $this->project->others_panel_audience = $audience;
        }
        $this->project->save();

        $field = new Fields();
        $field->name = 'locality';
        $field->type = 'text';
        $field->position = 1;
        $field->min_user_level = 5;
        $field->save();
        $this->project->fields()->attach($field->id);

        Specimen::create(['specimen_id' => '999080', 'project_id' => $this->project->id, 'images' => 'a.jpg']);

        $author = $this->user('user', 'Author');
        $row = new Collaborative_fields();
        $row->value = 'Boserup';
        $row->position = 1;
        $row->specimen_id = '999080';
        $row->field_id = $field->id;
        $row->user_id = $author->id;
        $row->points = 20;
        $row->status = 1;
        $row->project_id = $this->project->id;
        $row->save();
    }

    /** Who sees the panel for a given audience: root, admin, the project's manager, another project's manager, a regular user. */
    private function whoSees(?string $audience): array
    {
        $this->setUpProject($audience);

        $manager = $this->user('project_manager', 'Manager');
        $this->project->managers()->attach($manager->id);

        $viewers = [
            'root' => $this->user('root', 'Root'),
            'admin' => $this->user('admin', 'Admin'),
            'manager' => $manager,
            'other_manager' => $this->user('project_manager', 'Other'),
            'user' => $this->user('user', 'Regular'),
        ];

        $sees = [];
        foreach ($viewers as $label => $viewer) {
            $html = $this->actingAs($viewer)->get("/projects/{$this->project->id}/999080")->assertOk()->getContent();
            $sees[$label] = str_contains($html, 'Use this value');
        }

        return $sees;
    }

    public function test_the_default_is_what_it_always_was_root_admin_and_the_projects_managers(): void
    {
        $this->assertSame(
            ['root' => true, 'admin' => true, 'manager' => true, 'other_manager' => false, 'user' => false],
            $this->whoSees(null)
        );
    }

    public function test_off_shows_it_to_nobody(): void
    {
        $this->assertSame(
            ['root' => false, 'admin' => false, 'manager' => false, 'other_manager' => false, 'user' => false],
            $this->whoSees('off')
        );
    }

    public function test_root_only(): void
    {
        $this->assertSame(
            ['root' => true, 'admin' => false, 'manager' => false, 'other_manager' => false, 'user' => false],
            $this->whoSees('root')
        );
    }

    public function test_root_and_admins(): void
    {
        $this->assertSame(
            ['root' => true, 'admin' => true, 'manager' => false, 'other_manager' => false, 'user' => false],
            $this->whoSees('admin')
        );
    }

    public function test_all_users(): void
    {
        $this->assertSame(
            ['root' => true, 'admin' => true, 'manager' => true, 'other_manager' => true, 'user' => true],
            $this->whoSees('all')
        );
    }

    public function test_under_all_users_a_submitter_is_shown_as_they_chose_to_appear(): void
    {
        $this->setUpProject('all');
        $viewer = $this->user('user', 'Regular');
        $manager = $this->user('project_manager', 'Manager');
        $this->project->managers()->attach($manager->id);
        $author = User::where('name', 'Author')->firstOrFail();
        $page = "/projects/{$this->project->id}/999080";

        // visible by default: everybody sees the name
        $this->actingAs($viewer)->get($page)->assertOk()->assertSee('Author · 20 points', false);

        // the author switches the public name off
        $author->public_name = false;
        $author->save();

        $this->actingAs($viewer)->get($page)->assertOk()
            ->assertSee('hidden user · 20 points', false)->assertDontSee('Author');
        // the project's own people still know who it is, marked as hidden
        $this->actingAs($manager)->get($page)->assertOk()->assertSee('Author (hidden) · 20 points', false);
    }

    public function test_the_choice_is_saved_from_the_project_form_and_a_form_without_it_leaves_it_alone(): void
    {
        $admin = $this->user('admin', 'Admin');
        $this->setUpProject('admin');

        $this->actingAs($admin)->post('/projects', ['name' => 'P', 'project_id' => $this->project->id, 'others_panel_audience' => 'all']);
        $this->assertSame('all', $this->project->fresh()->others_panel_audience);

        // an unknown value falls back to the default
        $this->actingAs($admin)->post('/projects', ['name' => 'P', 'project_id' => $this->project->id, 'others_panel_audience' => 'everybody']);
        $this->assertSame('project_manager', $this->project->fresh()->others_panel_audience);

        // a form that does not carry the setting must not reset it
        $this->actingAs($admin)->post('/projects', ['name' => 'P', 'project_id' => $this->project->id, 'others_panel_audience' => 'root']);
        $this->actingAs($admin)->post('/projects', ['name' => 'P renamed', 'project_id' => $this->project->id]);
        $this->assertSame('root', $this->project->fresh()->others_panel_audience);
    }

    public function test_the_project_form_offers_the_choices_with_the_current_one_selected(): void
    {
        $this->setUpProject('admin');

        $html = view('projects.form', ['project' => $this->project->fresh(), 'api_schema' => []])->render();

        $this->assertStringContainsString('name="others_panel_audience"', $html);
        $this->assertMatchesRegularExpression('/<option value="admin" selected>/', $html);
        foreach (Project::OTHERS_PANEL_AUDIENCES as $choice) {
            $this->assertStringContainsString('<option value="' . $choice . '"', $html);
        }
    }
}
