<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Project;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Validate_fields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChooseValueTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private Fields $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = new Project();
        $this->project->name = 'P';
        $this->project->user_id = 1;
        $this->project->active = 1;
        $this->project->field_view_default = 'all';
        $this->project->save();

        $this->field = new Fields();
        $this->field->name = 'locality';
        $this->field->type = 'text';
        $this->field->position = 1;
        $this->field->min_user_level = 5;
        $this->field->save();
        $this->project->fields()->attach($this->field->id);

        Specimen::create(['specimen_id' => '999060', 'project_id' => $this->project->id, 'images' => 'a.jpg']);
    }

    private function user(int $category, string $name): User
    {
        $cat = User_categories::find($category) ?? new User_categories();
        $cat->id = $category;
        $cat->category = 'c' . $category;
        $cat->points = $category === 1 ? 60 : 20;
        $cat->save();

        $user = new User();
        $user->category = $category;
        $user->role = $category <= 1 ? 'admin' : 'user'; // the old convention (level 0/1 = admin) only decides the test user's role now
        $user->name = $name;
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->field_view = 'all';
        $user->save();

        return $user;
    }

    /** Goes through the real submit flow, so statuses and validation are what production would produce. */
    private function submit(User $user, string $value): void
    {
        $this->actingAs($user)->post('/store_user_response', [
            'project_id' => $this->project->id,
            'specimen_id' => '999060',
            'field' => [$this->field->id => [0 => $value]],
        ]);
    }

    private function choose(User $user, string $value)
    {
        return $this->actingAs($user)->postJson('/choose_value', [
            'specimen_id' => '999060',
            'project_id' => $this->project->id,
            'field_id' => $this->field->id,
            'position' => 1,
            'value' => $value,
        ]);
    }

    private function current(User $user): ?Collaborative_fields
    {
        return Collaborative_fields::where('user_id', $user->id)->where('specimen_id', '999060')->where('status', '>', 0)->first();
    }

    public function test_switching_before_anything_is_validated_changes_only_your_own_submission(): void
    {
        $ann = $this->user(5, 'Ann');
        $bob = $this->user(6, 'Bob');
        $this->submit($ann, 'X');
        $this->submit($bob, 'Y');

        $this->choose($bob, 'X')->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('X', $this->current($bob)->value);
        $this->assertSame(1, (int) $this->current($bob)->status);
        $this->assertSame('X', $this->current($ann)->value);
        $this->assertSame(1, Collaborative_fields::where('user_id', $bob->id)->where('status', 0)->count()); // the old 'Y'
    }

    public function test_an_admin_can_switch_even_after_validating_and_the_validation_follows(): void
    {
        $ann = $this->user(5, 'Ann');
        $admin = $this->user(1, 'Admin');
        $this->submit($ann, 'B');
        $this->submit($admin, 'A');   // 60 points: validates A, Ann's B is rejected

        $this->assertSame('A', Validate_fields::where('specimen_id', '999060')->where('status', 1)->value('value'));
        $this->assertSame(3, (int) Collaborative_fields::where('user_id', $ann->id)->value('status'));

        $this->choose($admin, 'B')->assertOk();

        $this->assertSame('B', Validate_fields::where('specimen_id', '999060')->where('status', 1)->value('value'));
        $this->assertSame(2, (int) Collaborative_fields::where('user_id', $ann->id)->value('status'));
        $this->assertSame('B', $this->current($admin)->value);
    }

    public function test_it_is_the_same_as_going_back_and_resubmitting_for_an_ordinary_user_too(): void
    {
        $ann = $this->user(5, 'Ann');
        $bob = $this->user(6, 'Bob');
        $cid = $this->user(7, 'Cid');
        $dan = $this->user(8, 'Dan');
        $this->submit($ann, 'X');
        $this->submit($bob, 'X');
        $this->submit($cid, 'X');   // 60 points: X is validated
        $this->submit($dan, 'Y');

        // what Dan's own resubmission of 'X' through the normal form leaves behind...
        $this->submit($dan, 'X');
        $viaForm = Collaborative_fields::where('user_id', $dan->id)->orderBy('id')->get(['value', 'status', 'points'])->toArray();

        // ...must be exactly what the button leaves behind
        Collaborative_fields::where('user_id', $dan->id)->delete();
        $this->submit($dan, 'Y');
        $this->choose($dan, 'X')->assertOk()->assertJson(['ok' => true]);
        $viaButton = Collaborative_fields::where('user_id', $dan->id)->orderBy('id')->get(['value', 'status', 'points'])->toArray();

        $this->assertSame($viaForm, $viaButton);
    }

    public function test_without_a_submission_of_your_own_there_is_nothing_to_switch(): void
    {
        $ann = $this->user(5, 'Ann');
        $bob = $this->user(6, 'Bob');
        $this->submit($ann, 'X');

        $this->choose($bob, 'X')->assertStatus(422);
    }

    public function test_the_different_values_page_offers_a_choice_and_no_longer_the_next_or_skip_buttons(): void
    {
        $ann = $this->user(5, 'Ann');
        $bob = $this->user(6, 'Bob');
        $this->submit($ann, 'X');
        $this->submit($bob, 'Y');

        $fid = $this->field->id;
        $response = $this->actingAs($bob)->withSession([
            'different_values' => [$fid => [1 => ['X' => 1]]],
            'equal_values' => [],
            'submitted_values' => [$fid => [1 => 'Y']],
            'next_form' => url('/projects/' . $this->project->id . '/999061'),
        ])->get('/projects/' . $this->project->id . '/999060');

        $html = $response->getContent();
        $response->assertOk()->assertSee('Keep this value')->assertSee('Use this value');
        $this->assertStringContainsString('id="conflict-groups"', $html);
        $this->assertStringContainsString('data-value="X"', $html);
        $this->assertStringNotContainsString('id="keep-all-values"', $html);   // a single line: no "keep all"
        $this->assertStringNotContainsString('next_form', $html);
        $this->assertSame(1, substr_count($html, '/projects/' . $this->project->id . '/start')); // only the hidden form's own Skip
    }
}
