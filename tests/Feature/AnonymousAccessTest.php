<?php

namespace Tests\Feature;

use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Specimen;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnonymousAccessTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private Fields $field;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $this->project = new Project();
        $this->project->name = 'P';
        $this->project->user_id = 0;
        $this->project->active = 1;
        $this->project->field_view_default = 'all';
        $this->project->save();

        $this->field = new Fields();
        $this->field->name = 'locality';
        $this->field->type = 'text';
        $this->field->position = 1;
        $this->field->min_user_level = 5;
        $this->field->autocomplete = 0;
        $this->field->save();
        $this->project->fields()->attach($this->field->id);

        Specimen::create(['specimen_id' => '999090', 'project_id' => $this->project->id, 'images' => 'a.jpg']);
    }

    private function mode(string $mode): void
    {
        $setting = Setting::current();
        $setting->anonymous_access = $mode;
        $setting->save();
    }

    private function user(string $role = 'user'): User
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

    private function submitAsVisitor()
    {
        return $this->post('/store_user_response', [
            'project_id' => $this->project->id,
            'specimen_id' => '999090',
            'field' => [$this->field->id => [0 => 'Boserup']],
        ]);
    }

    private function openPages(): array
    {
        return ['/', '/projects', '/projects/' . $this->project->id, '/projects/' . $this->project->id . '/999090', '/comments'];
    }

    // ---- login required (the default: how the platform is locked down today) ---------------

    public function test_by_default_everything_asks_for_a_login(): void
    {
        $this->assertSame('login_required', Setting::current()->anonymousAccess());

        foreach ($this->openPages() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->submitAsVisitor()->assertRedirect(route('login'));
    }

    public function test_an_unknown_value_counts_as_login_required(): void
    {
        $this->mode('whatever');

        $this->assertSame('login_required', Setting::current()->anonymousAccess());
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_an_ajax_request_from_a_visitor_gets_a_401_not_a_redirect(): void
    {
        $this->postJson('/choose_value', [])->assertUnauthorized();
    }

    public function test_logged_in_users_are_unaffected(): void
    {
        $this->actingAs($this->user())->get('/')->assertOk();
        $this->actingAs($this->user())->get('/projects/' . $this->project->id . '/999090')->assertOk();
    }

    // ---- anyone can browse -----------------------------------------------------------------

    public function test_browse_opens_the_read_only_pages_but_nothing_that_submits_or_administers(): void
    {
        $this->mode('browse');

        foreach ($this->openPages() as $url) {
            $this->get($url)->assertOk();
        }

        $this->submitAsVisitor()->assertRedirect(route('login'));
        $this->get('/autocomplete?id=' . $this->field->id . '&query=abc')->assertRedirect(route('login'));
        $this->assertSame(0, Collaborative_fields::count());
    }

    public function test_a_visitor_who_may_only_browse_is_told_and_the_submit_buttons_are_off(): void
    {
        $this->mode('browse');

        $this->get('/projects/' . $this->project->id . '/999090')->assertOk()->assertSee('Log in to submit');
    }

    public function test_a_visitor_can_read_the_comments_of_a_specimen_and_gets_no_delete_button(): void
    {
        $author = $this->user();
        $comment = new \App\Models\Comments();
        $comment->value = 'Looks like Boserup to me';
        $comment->specimen_id = 999090;
        $comment->field_id = $this->field->id;
        $comment->user_id = $author->id;
        $comment->status = 1;
        $comment->save();

        foreach (['browse', 'submit'] as $mode) {
            $this->mode($mode);
            \Illuminate\Support\Facades\Auth::logout();

            $this->get('/projects/' . $this->project->id . '/999090')->assertOk()
                ->assertSee('Looks like Boserup to me')->assertDontSee('btn btn-danger btn-sm delete_comment', false);
        }

        // the author does get it
        $this->actingAs($author)->get('/projects/' . $this->project->id . '/999090')->assertSee('btn btn-danger btn-sm delete_comment', false);
    }

    // ---- anyone can browse and submit ------------------------------------------------------

    public function test_an_anonymous_submission_is_tied_to_the_session_and_counts_with_the_lowest_level(): void
    {
        $this->mode('submit');

        $this->submitAsVisitor()->assertSessionHasNoErrors();

        $row = Collaborative_fields::firstOrFail();
        $this->assertSame('Boserup', $row->value);
        $this->assertFalse(is_numeric($row->user_id));          // a session id, not a user id
        $this->assertSame(20, (int) $row->points);              // the lowest level's points
        $this->assertSame($this->project->id, (int) $row->project_id);
    }

    public function test_the_page_does_not_disable_submitting_when_visitors_may_submit(): void
    {
        $this->mode('submit');

        $this->get('/projects/' . $this->project->id . '/999090')->assertOk()->assertDontSee('Log in to submit');
    }

    public function test_a_visitor_may_use_the_suggestions_when_submitting_is_open(): void
    {
        $this->mode('submit');

        $this->get('/autocomplete?id=' . $this->field->id . '&query=abc')->assertOk();
    }

    public function test_even_when_everything_is_open_administration_and_accounts_still_need_a_login(): void
    {
        $this->mode('submit');

        foreach (['/profile', '/users', '/dashboard', '/projects/create', '/projects/' . $this->project->id . '/edit', '/settings', '/all_submissions', '/conflicts/1', '/validated'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post('/submit_comment', ['comment_value' => 'hi'])->assertRedirect(route('login'));
        $this->post('/profile/avatar', [])->assertRedirect(route('login'));
    }

    public function test_the_admin_lists_cope_with_a_submission_that_belongs_to_no_user(): void
    {
        $this->mode('submit');
        $this->submitAsVisitor();

        $this->actingAs($this->user('admin'))->get('/all_submissions')->assertOk()->assertSee('guest');
    }

    // ---- who may change the setting --------------------------------------------------------

    public function test_root_and_admin_can_set_it_and_nobody_else(): void
    {
        foreach (['root', 'admin'] as $role) {
            $this->actingAs($this->user($role))->post('/settings/access', ['anonymous_access' => 'browse'])->assertRedirect('/settings');
            $this->assertSame('browse', Setting::current()->anonymousAccess());
            $this->mode('login_required');
        }

        foreach (['project_manager', 'user'] as $role) {
            $this->actingAs($this->user($role))->post('/settings/access', ['anonymous_access' => 'submit'])->assertForbidden();
        }
        $this->assertSame('login_required', Setting::current()->anonymousAccess());
    }

    public function test_only_the_known_choices_are_accepted(): void
    {
        $this->actingAs($this->user('root'))->post('/settings/access', ['anonymous_access' => 'open-sesame'])->assertSessionHasErrors('anonymous_access');
        $this->actingAs($this->user('root'))->post('/settings/access', [])->assertSessionHasErrors('anonymous_access');

        $this->assertSame('login_required', Setting::current()->anonymousAccess());
    }

    public function test_the_settings_page_shows_each_role_its_own_sections(): void
    {
        $this->actingAs($this->user('admin'))->get('/settings')->assertOk()
            ->assertSee('Access without logging in')->assertDontSee('Loading indicator');
        $this->actingAs($this->user('root'))->get('/settings')->assertOk()
            ->assertSee('Access without logging in')->assertSee('Loading indicator');
        $this->actingAs($this->user('user'))->get('/settings')->assertRedirect('/projects');
    }

    public function test_an_admin_cannot_change_the_branding(): void
    {
        $this->actingAs($this->user('admin'))->post('/settings', [])->assertForbidden();
    }
}
