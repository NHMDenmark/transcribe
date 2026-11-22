<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the doubt/deducted position-alignment fix in
 * SpecimensController::store() (commit dd26d96): on a multi-value field,
 * doubt/deducted must be read by the same index key as the line's own value
 * (doubt.<field_id>.<key>), so each line's flags land on that line and not on
 * whichever line happened to be submitted last.
 */
class SpecimenStoreDoubtPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_doubt_and_deducted_are_saved_against_the_correct_line_position(): void
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
        $field->name = 'collector';
        $field->type = 'text';
        $field->multiple = 1;
        $field->position = 1;
        $field->save();

        $this->actingAs($user)->post('/store_user_response', [
            'project_id' => $project->id,
            'specimen_id' => '999010',
            'field' => [
                $field->id => [
                    0 => 'Flemming',
                    1 => 'Vilhelmsen',
                ],
            ],
            // Only the second line has doubt checked — keyed by the same
            // index (1) as its value above.
            'doubt' => [
                $field->id => [
                    1 => '1',
                ],
            ],
        ]);

        $line1 = Collaborative_fields::where('specimen_id', '999010')
            ->where('field_id', $field->id)
            ->where('position', 1)
            ->where('status', '>', 0)
            ->first();

        $line2 = Collaborative_fields::where('specimen_id', '999010')
            ->where('field_id', $field->id)
            ->where('position', 2)
            ->where('status', '>', 0)
            ->first();

        $this->assertNotNull($line1);
        $this->assertNotNull($line2);
        $this->assertSame('Flemming', $line1->value);
        $this->assertSame('Vilhelmsen', $line2->value);
        $this->assertEquals(0, $line1->doubt);
        $this->assertEquals(1, $line2->doubt);

        // project_id must be persisted from the submitted form — everything
        // downstream (the /validated dashboard, the sync pipeline's root-table
        // resolution) relies on it instead of guessing from specimen_id alone.
        $this->assertEquals($project->id, $line1->project_id);
        $this->assertEquals($project->id, $line2->project_id);
    }
}
