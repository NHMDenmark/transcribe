<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * A project stores who created it (user_id). People who can manage the project see that, and who else manages it;
 * ordinary visitors do not.
 */
class ProjectOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $language = new Language();
        $language->iso2 = 'en';
        $language->master = 1;
        $language->save();
        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();
    }

    private function person(string $role, string $name): User
    {
        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = $name;
        $user->email = strtolower($name) . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function project(User $creator): Project
    {
        $project = new Project();
        $project->name = 'Moths';
        $project->user_id = $creator->id;
        $project->active = 1;
        $project->save();

        return $project;
    }

    public function test_the_project_knows_its_creator(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $project = $this->project($creator);

        $this->assertSame($creator->id, $project->creator->id);
    }

    public function test_those_who_manage_the_project_see_who_created_it_and_who_else_manages_it(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $helper = $this->person('project_manager', 'Davi');
        $admin = $this->person('admin', 'Ada');
        $project = $this->project($creator);
        $project->managers()->attach($helper->id);

        foreach (['/projects/' . $project->id, '/projects/' . $project->id . '/edit'] as $url) {
            Auth::logout();
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('Carla', $html, $url);
            $this->assertStringContainsString('carla@example.test', $html, $url);
            $this->assertStringContainsString('Davi', $html, $url);
        }

        // the creator, a manager of their own project, sees it as well
        Auth::logout();
        $this->actingAs($creator)->get('/projects/' . $project->id)->assertOk()->assertSee('Carla');
    }

    public function test_the_creator_is_in_the_list_of_managers_ticked_and_locked(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $helper = $this->person('project_manager', 'Davi');
        $admin = $this->person('admin', 'Ada');
        $project = $this->project($creator);

        $html = $this->actingAs($admin)->get('/projects/' . $project->id . '/edit')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*id="manager_creator"[^>]*checked[^>]*disabled|<input[^>]*id="manager_creator"[^>]*disabled[^>]*checked/', $html);
        $this->assertStringNotContainsString('id="manager_' . $creator->id . '"', $html, 'the creator is not offered a second time');
        $this->assertStringContainsString('id="manager_' . $helper->id . '"', $html);
    }

    public function test_saving_the_managers_never_takes_the_creator_out(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $helper = $this->person('project_manager', 'Davi');
        $admin = $this->person('admin', 'Ada');
        $project = $this->project($creator);

        // the form does not send the locked creator; only the ticked helper
        $this->actingAs($admin)->post('/projects', [
            'project_id' => $project->id,
            'name' => 'Moths',
            'active' => 'on',
            'managers_present' => 1,
            'managers' => [$helper->id],
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame($creator->id, $project->user_id);
        $this->assertTrue($creator->managesProject($project));
        $this->assertTrue($helper->managesProject($project));
    }

    public function test_the_creator_adds_a_manager_and_that_manager_can_add_another(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $second = $this->person('project_manager', 'Davi');
        $third = $this->person('project_manager', 'Eva');
        $project = $this->project($creator);
        $form = ['project_id' => $project->id, 'name' => 'Moths', 'active' => 'on', 'managers_present' => 1];

        // the creator sees the list in the form
        $html = $this->actingAs($creator)->get('/projects/' . $project->id . '/edit')->assertOk()->getContent();
        $this->assertStringContainsString('id="manager_' . $second->id . '"', $html);

        $this->actingAs($creator)->post('/projects', $form + ['managers' => [$second->id]])->assertRedirect();
        $this->assertTrue($second->managesProject($project->fresh()));

        // the one they added has the same power, on the list too
        Auth::logout();
        $this->actingAs($second)->post('/projects', $form + ['managers' => [$second->id, $third->id]])->assertRedirect();
        $this->assertTrue($third->managesProject($project->fresh()));
    }

    public function test_a_manager_can_remove_another_but_never_the_creator(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $second = $this->person('project_manager', 'Davi');
        $third = $this->person('project_manager', 'Eva');
        $project = $this->project($creator);
        $project->managers()->attach([$second->id, $third->id]);

        // the second one unticks the third, and has no way to send the creator out
        $this->actingAs($second)->post('/projects', [
            'project_id' => $project->id, 'name' => 'Moths', 'active' => 'on', 'managers_present' => 1, 'managers' => [$second->id],
        ])->assertRedirect();

        $project = $project->fresh();
        $this->assertFalse($third->managesProject($project));
        $this->assertTrue($creator->managesProject($project));
        $this->assertSame($creator->id, $project->user_id);
    }

    public function test_a_manager_is_asked_before_unticking_themselves(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $second = $this->person('project_manager', 'Davi');
        $third = $this->person('project_manager', 'Eva');
        $project = $this->project($creator);
        $project->managers()->attach([$second->id, $third->id]);

        $html = $this->actingAs($second)->get('/projects/' . $project->id . '/edit')->assertOk()->getContent();

        // only their own box carries the marker, and the script that asks is on the page
        $this->assertStringContainsString('id="manager_' . $second->id . '" value="' . $second->id . '" checked data-self="1"', $html);
        $this->assertSame(1, substr_count($html, 'data-self="1"'));
        $this->assertStringContainsString('You will lose access to this project when you save. Continue?', $html);
        $this->assertStringContainsString("input[data-self]", $html);
    }

    public function test_an_administrator_is_not_asked_because_they_keep_access(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $admin = $this->person('admin', 'Ada');
        $project = $this->project($creator);

        $html = $this->actingAs($admin)->get('/projects/' . $project->id . '/edit')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-self="1"', $html);
    }

    public function test_the_creator_of_a_new_project_can_name_its_managers_right_away(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $helper = $this->person('project_manager', 'Davi');

        $html = $this->actingAs($creator)->get('/projects/create')->assertOk()->getContent();
        $this->assertStringContainsString('name="managers_present"', $html);
        $this->assertStringNotContainsString('id="manager_creator"', $html, 'a project that does not exist yet has no creator row');

        $this->actingAs($creator)->post('/projects', ['name' => 'New', 'active' => 'on', 'managers_present' => 1, 'managers' => [$helper->id]])->assertRedirect();

        $project = Project::where('name', 'New')->firstOrFail();
        $this->assertSame($creator->id, $project->user_id);
        $this->assertTrue($helper->managesProject($project));
    }

    public function test_an_ordinary_user_can_neither_create_a_project_nor_see_the_list(): void
    {
        $volunteer = $this->person('user', 'Vera');

        $this->actingAs($volunteer)->get('/projects/create')->assertForbidden();
        $this->actingAs($volunteer)->post('/projects', ['name' => 'Sneaky', 'managers_present' => 1, 'managers' => [$volunteer->id]])->assertForbidden();
        $this->assertSame(0, Project::where('name', 'Sneaky')->count());
    }

    public function test_the_list_of_projects_shows_it_to_those_who_manage_them(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $volunteer = $this->person('user', 'Vera');
        $this->project($creator);

        $this->actingAs($creator)->get('/projects')->assertOk()->assertSee('carla@example.test');

        Auth::logout();
        $this->actingAs($volunteer)->get('/projects')->assertOk()->assertDontSee('carla@example.test');
    }

    public function test_an_ordinary_user_does_not_see_the_creator(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $volunteer = $this->person('user', 'Vera');
        $project = $this->project($creator);

        $this->actingAs($volunteer)->get('/projects/' . $project->id)->assertOk()->assertDontSee('carla@example.test')->assertDontSee('Carla');
    }

    public function test_a_creator_whose_account_is_gone_is_said_so_and_does_not_break_the_page(): void
    {
        $creator = $this->person('project_manager', 'Carla');
        $admin = $this->person('admin', 'Ada');
        $project = $this->project($creator);
        $creator->delete();

        $this->actingAs($admin)->get('/projects/' . $project->id)->assertOk()->assertSee('a user who no longer exists');
    }
}
