<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SpecimenStoreDisplayedAtTest extends TestCase
{
    use RefreshDatabase;

    private function submit(?string $displayedAt): Collaborative_fields
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
        $field->name = 'locality';
        $field->type = 'text';
        $field->multiple = 0;
        $field->position = 1;
        $field->save();

        $payload = [
            'project_id' => $project->id,
            'specimen_id' => '999020',
            'field' => [$field->id => [0 => 'Boserup']],
        ];
        if ($displayedAt !== null) {
            $payload['displayed_at'] = $displayedAt;
        }

        $this->actingAs($user)->post('/store_user_response', $payload);

        return Collaborative_fields::where('specimen_id', '999020')->firstOrFail();
    }

    public function test_displayed_at_from_the_form_is_stored_on_the_submission(): void
    {
        $shown = '2026-10-08 10:00:00';
        $row = $this->submit(Crypt::encryptString($shown));

        $this->assertSame($shown, $row->displayed_at);
    }

    public function test_tampered_or_missing_displayed_at_is_stored_as_null(): void
    {
        $this->assertNull($this->submit('2026-10-08 10:00:00')->displayed_at);
    }
}
