<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SubmissionsExportTest extends TestCase
{
    use RefreshDatabase;

    private Fields $field;
    private Project $alpha;
    private Project $beta;
    private User $volunteer;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $this->field = new Fields();
        $this->field->name = 'locality';
        $this->field->type = 'text';
        $this->field->position = 1;
        $this->field->min_user_level = 5;
        $this->field->autocomplete = 0;
        $this->field->save();

        foreach (['alpha' => 'Alpha project', 'beta' => 'Beta project'] as $key => $name) {
            $p = new Project();
            $p->name = $name;
            $p->user_id = 1;
            $p->active = 1;
            $p->field_view_default = 'all';
            $p->save();
            $this->$key = $p;
        }

        $this->volunteer = $this->user('user');
    }

    private function user(string $role): User
    {
        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = ucfirst($role);
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function submission(string $value, Project $project, ?string $displayedAt = null): Collaborative_fields
    {
        $c = new Collaborative_fields();
        $c->value = $value;
        $c->position = 0;
        $c->specimen_id = '9' . random_int(10000, 99999);
        $c->field_id = $this->field->id;
        $c->user_id = (string) $this->volunteer->id;
        $c->project_id = $project->id;
        $c->status = 1;
        $c->points = 20;
        $c->displayed_at = $displayedAt;
        $c->created_at = now();
        $c->save();

        return $c;
    }

    private function csvRows(string $url, User $as): array
    {
        $response = $this->actingAs($as)->get($url)->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);   // the byte order mark, so spreadsheets read the UTF-8

        $lines = array_values(array_filter(array_map('str_getcsv', explode("\n", substr($content, 3))), fn($l) => $l !== [null]));
        $header = array_shift($lines);

        return array_map(fn($l) => array_combine($header, $l), $lines);
    }

    public function test_an_ordinary_user_cannot_see_or_export_the_submissions_of_others(): void
    {
        $this->submission('x', $this->alpha);

        foreach (['/all_submissions', '/all_submissions/csv', '/all_submissions/json'] as $url) {
            $this->actingAs($this->user('user'))->get($url)->assertForbidden();
            $this->actingAs($this->user('admin'))->get($url)->assertOk();
            $this->actingAs($this->user('project_manager'))->get($url)->assertOk();
            Auth::logout();
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_the_csv_has_one_row_per_submission_with_names_and_time_spent(): void
    {
        $shown = now()->subSeconds(90)->format('Y-m-d H:i:s');
        $this->submission('Boserup, Fyn', $this->alpha, $shown);
        $this->submission('Åbenrå', $this->beta);

        $rows = $this->csvRows('/all_submissions/csv', $this->user('admin'));

        $this->assertCount(2, $rows);
        $first = collect($rows)->firstWhere('value', 'Boserup, Fyn');
        $this->assertSame('Alpha project', $first['project_name']);
        $this->assertSame('locality', $first['field_name']);
        $this->assertSame('User', $first['user_name']);
        $this->assertEqualsWithDelta(90, (int) $first['seconds_spent'], 3);
        $this->assertSame('', collect($rows)->firstWhere('value', 'Åbenrå')['seconds_spent']);   // no displayed_at, no time
    }

    public function test_the_export_follows_the_filters_of_the_list_and_the_starting_record(): void
    {
        $a = $this->submission('In alpha', $this->alpha);
        $this->submission('In beta', $this->beta);
        $c = $this->submission('Also alpha', $this->alpha);
        $admin = $this->user('admin');

        $this->assertSame(['In alpha', 'Also alpha'], array_column($this->csvRows('/all_submissions/csv?project_id=' . $this->alpha->id, $admin), 'value'));
        $this->assertSame(['Also alpha'], array_column($this->csvRows('/all_submissions/csv?project_id=' . $this->alpha->id . '&start_id=' . $a->id, $admin), 'value'));
        $this->assertSame(['In beta'], array_column($this->csvRows('/all_submissions/csv?value=beta', $admin), 'value'));
        $this->assertSame([], $this->csvRows('/all_submissions/csv?value=nothing-like-this', $admin));
    }

    public function test_the_json_is_paged_and_filtered_too(): void
    {
        foreach (range(1, 5) as $i) {
            $this->submission('Alpha ' . $i, $this->alpha);
        }
        $this->submission('Beta only', $this->beta);
        $admin = $this->user('admin');

        $page = $this->actingAs($admin)->getJson('/all_submissions/json?project_id=' . $this->alpha->id . '&per_page=2')->assertOk()->json();

        $this->assertCount(2, $page['data']);
        $this->assertNotNull($page['next_page_url']);
        $this->assertStringContainsString('project_id=' . $this->alpha->id, $page['next_page_url']);
        $this->assertSame('Alpha project', $page['data'][0]['project_name']);
        $this->assertArrayHasKey('seconds_spent', $page['data'][0]);
        $this->assertNotContains('Beta only', array_column($page['data'], 'value'));
    }

    public function test_the_list_offers_the_export_with_its_current_filters_and_only_to_those_who_may(): void
    {
        $this->submission('x', $this->alpha);

        $html = $this->actingAs($this->user('admin'))->get('/all_submissions?project_id=' . $this->alpha->id . '&sort=value&page=1')->assertOk()->getContent();

        $this->assertStringContainsString('id="exportCSV"', $html);
        $this->assertStringContainsString('id="exportJSON"', $html);
        // the filter travels to the export; the sort and the page do not
        $this->assertStringContainsString('all_submissions/csv?project_id=' . $this->alpha->id, $html);
        $this->assertStringNotContainsString('all_submissions/csv?project_id=' . $this->alpha->id . '&amp;sort', $html);

    }

    // ---- a project manager: the submissions of their own projects ----------------------------

    private function manager(): User
    {
        return $this->user('project_manager');
    }

    private function managerWithProject(): array
    {
        $manager = $this->manager();
        $mine = new Project();
        $mine->name = 'Manager project';
        $mine->user_id = $manager->id;
        $mine->active = 1;
        $mine->field_view_default = 'all';
        $mine->save();

        return [$manager, $mine];
    }

    public function test_a_project_manager_sees_only_the_submissions_of_their_own_projects(): void
    {
        [$manager, $mine] = $this->managerWithProject();
        $this->submission('In the managers project', $mine);
        $this->submission('In alpha', $this->alpha);
        $this->submission('In beta', $this->beta);

        $this->actingAs($manager)->get('/all_submissions')->assertOk()
            ->assertSee('In the managers project')->assertDontSee('In alpha')->assertDontSee('In beta')
            ->assertSee('Manager project')->assertDontSee('Alpha project');

        // asking for another project, by the filter, shows nothing of it
        $this->actingAs($manager)->get('/all_submissions?project_id=' . $this->alpha->id)->assertOk()->assertDontSee('In alpha');
    }

    public function test_a_project_given_to_a_manager_counts_as_theirs(): void
    {
        $manager = $this->manager();
        $manager->managedProjects()->attach($this->beta->id);
        $this->submission('In beta', $this->beta);
        $this->submission('In alpha', $this->alpha);

        $this->actingAs($manager)->get('/all_submissions')->assertSee('In beta')->assertDontSee('In alpha');
    }

    public function test_a_project_manager_exports_only_the_submissions_of_their_own_projects(): void
    {
        [$manager, $mine] = $this->managerWithProject();
        $this->submission('In the managers project', $mine);
        $this->submission('In alpha', $this->alpha);

        $this->assertSame(['In the managers project'], array_column($this->csvRows('/all_submissions/csv', $manager), 'value'));
        // naming another project in the filter does not open it
        $this->assertSame([], $this->csvRows('/all_submissions/csv?project_id=' . $this->alpha->id, $manager));

        $json = $this->actingAs($manager)->getJson('/all_submissions/json')->assertOk()->json('data');
        $this->assertSame(['In the managers project'], array_column($json, 'value'));
    }

    public function test_a_project_manager_gets_the_list_in_the_menu_but_not_the_user_stats(): void
    {
        $html = $this->actingAs($this->manager())->get('/projects')->assertOk()->getContent();

        $this->assertStringContainsString('href="' . url('all_submissions') . '"', $html);
        $this->assertStringNotContainsString('href="' . url('all_stats') . '"', $html);
        $this->actingAs($this->manager())->get('/all_stats')->assertForbidden();
    }
    // ---- one's own submissions ---------------------------------------------------------------

    private function otherPersonsSubmission(string $value): void
    {
        $other = $this->user('user');
        $c = $this->submission($value, $this->alpha);
        $c->user_id = (string) $other->id;
        $c->save();
    }

    public function test_everybody_can_export_their_own_submissions_and_only_those(): void
    {
        $this->submission('Mine in alpha', $this->alpha);
        $this->submission('Mine in beta', $this->beta);
        $this->otherPersonsSubmission('Somebody elses');

        $rows = $this->csvRows('/dashboard/csv', $this->volunteer);
        $this->assertEqualsCanonicalizing(['Mine in alpha', 'Mine in beta'], array_column($rows, 'value'));

        // the filters of the list apply here too
        $this->assertSame(['Mine in beta'], array_column($this->csvRows('/dashboard/csv?project_id=' . $this->beta->id, $this->volunteer), 'value'));

        // a project manager, and even an admin, only get their own: nothing, here
        $this->assertSame([], $this->csvRows('/dashboard/csv', $this->user('project_manager')));
        $this->assertSame([], $this->csvRows('/dashboard/csv', $this->user('admin')));
    }

    public function test_the_own_json_is_only_their_own_too(): void
    {
        $this->submission('Mine', $this->alpha);
        $this->otherPersonsSubmission('Somebody elses');

        $data = $this->actingAs($this->volunteer)->getJson('/dashboard/json')->assertOk()->json('data');

        $this->assertSame(['Mine'], array_column($data, 'value'));
        $this->assertSame('Alpha project', $data[0]['project_name']);
    }

    public function test_one_cannot_reach_the_others_through_the_own_export(): void
    {
        $this->otherPersonsSubmission('Somebody elses');

        // asking for another user, by any filter, changes nothing: it is always the signed-in user's own
        $this->assertSame([], $this->csvRows('/dashboard/csv?user=' . urlencode('@example.test') . '&specimen_id=', $this->volunteer));
        Auth::logout();
        $this->get('/dashboard/csv')->assertRedirect(route('login'));
    }

    public function test_the_own_list_offers_the_export_pointing_at_the_own_export(): void
    {
        $this->submission('Mine', $this->alpha);

        $mine = $this->actingAs($this->volunteer)->get('/dashboard?project_id=' . $this->alpha->id)->assertOk()->getContent();

        $this->assertStringContainsString('id="exportCSV"', $mine);
        $this->assertStringContainsString('dashboard/csv?project_id=' . $this->alpha->id, $mine);
        $this->assertStringContainsString('dashboard/json?project_id=' . $this->alpha->id, $mine);
        $this->assertStringNotContainsString('all_submissions/csv', $mine);
    }
}
