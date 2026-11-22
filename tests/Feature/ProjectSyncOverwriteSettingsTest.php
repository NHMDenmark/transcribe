<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSyncOverwriteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $category = new User_categories();
        $category->category = 'admin';
        $category->points = 0;
        $category->save();

        $user = new User();
        $user->category = 0;
        $user->role = 'admin';
        $user->name = 'Admin';
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->field_view = 'all';
        $user->save();

        return $user;
    }

    public function test_the_settings_are_saved_from_the_project_form(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->post('/projects', [
            'name' => 'P',
            'sync_overwrite' => 'keep_existing',
            'sync_compare_case_insensitive' => '1',
            'sync_compare_round_decimals' => '4',
        ]);

        $project = Project::where('name', 'P')->firstOrFail();
        $this->assertSame('keep_existing', $project->sync_overwrite);
        $this->assertEquals(1, $project->sync_compare_case_insensitive);
        $this->assertSame(4, (int) $project->sync_compare_round_decimals);
    }

    public function test_anything_else_falls_back_to_overwrite_and_exact_comparison(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->post('/projects', ['name' => 'Q', 'sync_overwrite' => 'bogus', 'sync_compare_round_decimals' => '']);

        $project = Project::where('name', 'Q')->firstOrFail();
        $this->assertSame('overwrite', $project->sync_overwrite);
        $this->assertEquals(0, $project->sync_compare_case_insensitive);
        $this->assertNull($project->sync_compare_round_decimals);
    }

    public function test_the_form_shows_the_current_choice(): void
    {
        $project = new Project();
        $project->name = 'R';
        $project->user_id = 1;
        $project->active = 1;
        $project->sync_overwrite = 'keep_existing';
        $project->save();

        $html = view('projects.form', ['project' => $project->fresh(), 'api_schema' => []])->render();

        $this->assertStringContainsString('name="sync_overwrite"', $html);
        $this->assertMatchesRegularExpression('/<option value="keep_existing" selected>/', $html);
    }
}
