<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the specimen/project resolution fix in
 * SpecimensController::show() (commit dd26d96): a specimen_id can have a row
 * under more than one project, and a project can be deleted while specimens
 * still reference it. show() must resolve the row for the project actually
 * requested in the URL, and degrade gracefully instead of crashing when the
 * resolved project no longer exists.
 */
class SpecimenShowResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $category = new User_categories();
        $category->category = 'tester';
        $category->points = 0;
        $category->save();

        $user = new User();
        $user->category = $category->id;
        $user->name = 'Test User';
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->field_view = 'all';
        $user->save();

        return $user;
    }

    private function makeProject(int $userId): Project
    {
        $project = new Project();
        $project->name = 'Test Project';
        $project->user_id = $userId;
        $project->active = 1;
        $project->field_view_default = 'all';
        $project->save();

        return $project;
    }

    public function test_resolves_the_row_for_the_project_requested_in_the_url(): void
    {
        $user = $this->makeUser();
        $projectA = $this->makeProject($user->id);
        $projectB = $this->makeProject($user->id);

        // Same specimen_id, one row per project — this is the legitimate
        // "belongs to more than one project" case.
        Specimen::create(['specimen_id' => '999001', 'project_id' => $projectA->id, 'images' => 'x.jpg']);
        Specimen::create(['specimen_id' => '999001', 'project_id' => $projectB->id, 'images' => 'x.jpg']);

        $response = $this->actingAs($user)->get("/projects/{$projectB->id}/999001");

        // Must render project B's page directly, not redirect to project A's
        // row (which ->first() alone would pick arbitrarily) or to "no project".
        $response->assertOk();
    }

    public function test_degrades_to_no_project_view_when_the_resolved_project_was_deleted(): void
    {
        $user = $this->makeUser();
        $realProject = $this->makeProject($user->id);
        $deletedProjectId = $realProject->id + 1000; // guaranteed not to exist

        // The specimen row still points at a project that no longer exists.
        Specimen::create(['specimen_id' => '999002', 'project_id' => $deletedProjectId, 'images' => 'x.jpg']);

        $response = $this->actingAs($user)->get("/projects/{$deletedProjectId}/999002");

        // Before dd26d96 this fataled with "Call to a member function fields()
        // on null". It must now render (degrading to the pseudo "no project"),
        // not 500 and not redirect away.
        $response->assertOk();
    }

    public function test_redirects_to_no_project_when_the_specimen_has_no_row_for_the_requested_project(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user->id);
        $unrelatedProjectId = $project->id + 1000;

        Specimen::create(['specimen_id' => '999003', 'project_id' => $project->id, 'images' => 'x.jpg']);

        $response = $this->actingAs($user)->get("/projects/{$unrelatedProjectId}/999003");

        $response->assertRedirect("/projects/0/999003/");
    }

    public function test_redirects_to_projects_list_when_the_specimen_does_not_exist_at_all(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/projects/1/does-not-exist');

        $response->assertRedirect('/projects');
    }
}
