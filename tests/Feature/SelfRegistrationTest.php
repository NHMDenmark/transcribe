<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * An installation is "by invitation" until an administrator allows people to create their own account (Settings), and
 * each project may sync into its own Specify collection.
 */
class SelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $language = new Language();
        $language->iso2 = 'en';
        $language->master = 1;
        $language->save();
        foreach ([[9, 'Validator', null, 60], [1, 'Basic', 0, 10], [2, 'Beginner', 10, 20]] as [$id, $name, $validations, $points]) {
            $cat = new User_categories();
            $cat->id = $id;
            $cat->category = $name;
            $cat->validations_required = $validations;
            $cat->points = $points;
            $cat->save();
        }
    }

    private function admin(string $role = 'admin'): User
    {
        $user = new User();
        $user->category = 1;
        $user->role = $role;
        $user->name = ucfirst($role);
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function allow(bool $allowed): void
    {
        $setting = Setting::current();
        $setting->self_registration = $allowed;
        $setting->save();
    }

    private function form(): array
    {
        return ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'];
    }

    public function test_by_default_nobody_can_register_and_there_is_no_link(): void
    {
        $this->assertFalse(Setting::current()->allowsRegistration());

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->form())->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.test']);
        $this->assertStringNotContainsString(route('register'), $this->get('/login')->getContent());
    }

    public function test_once_allowed_the_link_shows_and_the_account_starts_at_the_lowest_level_as_a_plain_user(): void
    {
        $this->allow(true);
        Notification::fake();

        $this->assertStringContainsString(route('register'), $this->get('/login')->getContent());
        $this->get('/register')->assertOk();
        $this->post('/register', $this->form())->assertRedirect('/home');

        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertSame(1, (int) $user->category, 'the level that needs the fewest validations, never the validator level');
        $this->assertSame('user', $user->role);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_the_registration_is_validated(): void
    {
        $this->allow(true);

        $this->post('/register', ['name' => 'Ada', 'email' => 'not-an-address', 'password' => 'short', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertSame(0, User::count());
    }

    public function test_switching_it_off_again_closes_the_door_but_keeps_the_accounts(): void
    {
        $this->allow(true);
        $this->post('/register', $this->form());
        Auth::logout();
        $this->allow(false);

        $this->get('/register')->assertNotFound();
        $this->assertSame(1, User::where('email', 'ada@example.test')->count());
    }

    public function test_the_setting_is_changed_by_administrators_only(): void
    {
        $this->actingAs($this->admin('user'))->post('/settings/registration', ['self_registration' => '1'])->assertForbidden();
        $this->assertFalse(Setting::current()->allowsRegistration());

        Auth::logout();
        $this->actingAs($this->admin('admin'))->post('/settings/registration', ['self_registration' => '1'])->assertRedirect('/settings');
        $this->assertTrue(Setting::current()->allowsRegistration());

        $this->post('/settings/registration', [])->assertRedirect('/settings');
        $this->assertFalse(Setting::current()->allowsRegistration(), 'an unchecked switch sends nothing, and means off');
    }

    public function test_the_settings_page_shows_the_switch(): void
    {
        $html = $this->actingAs($this->admin())->get('/settings')->assertOk()->getContent();

        $this->assertStringContainsString('name="self_registration"', $html);
        $this->assertStringNotContainsString('name="self_registration" id="self_registration" value="1" checked', $html);
    }

    // ---- the collection of a project -------------------------------------------------------------------------

    public function test_a_project_remembers_the_collection_it_syncs_into(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/projects', ['name' => 'Moths', 'specify_collection' => '4']);
        $project = Project::where('name', 'Moths')->firstOrFail();
        $this->assertSame('4', $project->specify_collection);

        $html = $this->get('/projects/' . $project->id . '/edit')->assertOk()->getContent();
        $this->assertStringContainsString('name="specify_collection"', $html);
        $this->assertMatchesRegularExpression('/name="specify_collection" value="4"/', $html);

        // empty goes back to the default of the installation
        $this->post('/projects', ['name' => 'Moths', 'project_id' => $project->id, 'specify_collection' => '']);
        $this->assertNull($project->fresh()->specify_collection);
    }

    public function test_the_login_to_specify_uses_the_collection_asked_for_and_remembers_it(): void
    {
        config(['services.specify.url' => '', 'services.specify.collection' => '9']);

        // nothing is reachable here, but the collection is what the session says it is logged in to
        @API_login('4');
        $this->assertSame('4', session('specify_collection'));

        @API_login();
        $this->assertSame('9', session('specify_collection'), 'without a project collection, the installation default');
    }
}
