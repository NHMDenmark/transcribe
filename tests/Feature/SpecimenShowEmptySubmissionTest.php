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

/**
 * A user's own submission with an empty (or "0") value and no validated value
 * to fall back on used to 500 the specimen page with 'Undefined array key
 * "valid_value"'.
 */
class SpecimenShowEmptySubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function setUpOwnSubmission(string $value): array
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

        $project = new Project();
        $project->name = 'Test Project';
        $project->user_id = $user->id;
        $project->active = 1;
        $project->field_view_default = 'all';
        $project->save();

        $field = new Fields();
        $field->name = 'endDate';
        $field->min_user_level = $category->id;
        $field->type = 'text';
        $field->multiple = 0;
        $field->position = 1;
        $field->save();
        $project->fields()->attach($field->id);

        Specimen::create(['specimen_id' => '999030', 'project_id' => $project->id, 'images' => 'x.jpg']);

        $row = new Collaborative_fields();
        $row->value = $value;
        $row->position = 1;
        $row->specimen_id = '999030';
        $row->field_id = $field->id;
        $row->user_id = $user->id;
        $row->points = 0;
        $row->project_id = $project->id;
        $row->save();

        return [$user, $project];
    }

    public function test_page_renders_when_own_submission_is_empty_and_nothing_is_validated(): void
    {
        [$user, $project] = $this->setUpOwnSubmission('');

        $this->actingAs($user)->get("/projects/{$project->id}/999030")->assertOk();
    }

    public function test_page_renders_and_keeps_the_users_zero_when_nothing_is_validated(): void
    {
        [$user, $project] = $this->setUpOwnSubmission('0');

        $this->actingAs($user)->get("/projects/{$project->id}/999030")->assertOk();
    }
}
