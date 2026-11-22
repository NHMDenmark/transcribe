<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** The guides of docs/ shown inside the application, each to the roles they are meant for. */
class HelpTest extends TestCase
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

    private function as(string $role): self
    {
        Auth::logout();
        $user = new User();
        $user->category = 5;
        $user->role = $role;
        $user->name = ucfirst($role);
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $this->actingAs($user);
    }

    public function test_every_signed_in_person_gets_the_guide_for_contributors(): void
    {
        $html = $this->as('user')->get('/help')->assertOk()->getContent();

        $this->assertStringContainsString('Transcribing: a guide for contributors', $html);
        $this->assertStringNotContainsString('Administration guide', $html);

        $this->as('user')->get('/help/transcribing')->assertOk();
    }

    public function test_the_guides_of_the_administration_are_for_those_who_administer(): void
    {
        $this->as('user')->get('/help/administration')->assertNotFound();
        $this->as('user')->get('/help/configuration')->assertNotFound();

        $this->as('project_manager')->get('/help/administration')->assertOk()->assertSee('Administration guide');
        $this->as('project_manager')->get('/help/configuration')->assertNotFound();

        $this->as('admin')->get('/help/configuration')->assertOk()->assertSee('The file .env');
        $this->as('root')->get('/help/administration')->assertOk();
    }

    public function test_the_menu_of_the_help_lists_only_what_the_person_may_read(): void
    {
        $user = $this->as('user')->get('/help')->getContent();
        $this->assertStringContainsString('help/transcribing', $user);
        $this->assertStringNotContainsString('help/administration', $user);

        $admin = $this->as('admin')->get('/help/transcribing')->getContent();
        $this->assertStringContainsString('help/administration', $admin);
        $this->assertStringContainsString('help/configuration', $admin);
    }

    public function test_visitors_are_asked_to_sign_in_and_unknown_pages_are_not_found(): void
    {
        Auth::logout();
        $this->get('/help')->assertRedirect();

        $this->as('root')->get('/help/installation')->assertNotFound();
        $this->as('root')->get('/help/..%2F..%2F.env')->assertNotFound();
    }

    public function test_the_contents_of_a_guide_link_to_its_sections_and_to_the_other_guides(): void
    {
        $html = $this->as('root')->get('/help/administration')->getContent();

        // the list at the top points to ids that exist
        $this->assertStringContainsString('href="#the-big-picture"', $html);
        $this->assertStringContainsString('id="the-big-picture"', $html);
        $this->assertStringContainsString('id="how-a-value-becomes-valid"', $html);

        // a link to another guide opens that guide inside the application
        $this->assertStringContainsString('href="' . url('help') . '/configuration#settings-page"', $html);
        $this->assertStringNotContainsString('configuration.md', $html);

        // the guides about servers and code are not in the application: their links are plain text
        $installation = $this->as('root')->get('/help/configuration')->getContent();
        $this->assertStringNotContainsString('installation.md', $installation);

        // tables are styled
        $this->assertStringContainsString('<table class="table table-sm table-bordered', $html);
    }

    public function test_the_user_menu_has_a_help_entry(): void
    {
        $this->as('user')->get('/help')->assertSee(url('help'), false);
    }
}
