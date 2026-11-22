<?php

namespace Tests\Feature;

use App\Http\Controllers\HelpController;
use App\Models\Language;
use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The "?" hints next to the settings where a decision is taken: a short text and a link to the section of the guide
 * that explains the logic. They are listed in config/help.php.
 */
class HelpHintTest extends TestCase
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

        return $this->actingAs($this->person($role));
    }

    private function person(string $role): User
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

    // ---- the registry ---------------------------------------------------------------------------------------

    public function test_every_hint_points_at_a_section_that_exists_in_its_guide(): void
    {
        foreach (config('help.hints') as $key => $hint) {
            $html = $this->as('root')->get('/help/' . $hint['guide'])->assertOk()->getContent();

            $this->assertStringContainsString('id="' . $hint['anchor'] . '"', $html, "hint $key: section {$hint['anchor']} of {$hint['guide']}");
        }
    }

    public function test_the_texts_fit_in_a_dialog_identifier_and_are_not_repeated(): void
    {
        $texts = [];
        foreach (config('help.hints') as $key => $hint) {
            // the text is the identifier of its translations, and the column holds 200 characters
            $this->assertLessThanOrEqual(190, mb_strlen($hint['text']), $key);
            $this->assertNotContains($hint['text'], $texts, $key . ' repeats another hint');
            $texts[] = $hint['text'];
        }
    }

    public function test_every_hint_used_in_a_view_exists_and_every_hint_is_used(): void
    {
        $used = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (preg_match_all("/help_hint\('([a-z_]+)'/", (string) file_get_contents($file->getPathname()), $m)) {
                $used = array_merge($used, $m[1]);
            }
        }
        $used = array_unique($used);

        $this->assertSame([], array_values(array_diff($used, array_keys(config('help.hints')))), 'a view asks for a hint that is not in config/help.php');
        $this->assertSame([], array_values(array_diff(array_keys(config('help.hints')), $used)), 'a hint in config/help.php is not used by any view');
    }

    // ---- on the pages ---------------------------------------------------------------------------------------

    public function test_a_page_with_hints_carries_their_style_and_script_once(): void
    {
        $html = $this->as('admin')->get('/fields')->assertOk()->getContent();

        $this->assertGreaterThan(5, substr_count($html, 'class="help-hint"'));
        $this->assertSame(1, substr_count($html, 'window.__helpHints = true'));
        $this->assertSame(1, substr_count($html, '.help-hint { position: relative;'), 'the style is written once, not with every hint');
    }

    public function test_the_field_form_explains_its_decisions(): void
    {
        $html = $this->as('admin')->get('/fields')->getContent();

        foreach (['field_type', 'field_options', 'field_choices_separator', 'field_multiple', 'field_min_level', 'field_explode', 'field_na_defaults'] as $key) {
            $this->assertStringContainsString(e(config("help.hints.$key.text")), $html, $key);
        }
        $this->assertStringContainsString('href="' . url('help/administration') . '#field-types"', $html);
    }

    public function test_the_hint_opens_the_guide_in_a_new_tab_so_that_nothing_typed_is_lost(): void
    {
        $html = $this->as('admin')->get('/settings')->getContent();

        $this->assertMatchesRegularExpression('/<a class="help-hint-more" target="_blank" rel="noopener" href="[^"]*help\/configuration#access-for-visitors"/', $html);
    }

    public function test_the_project_form_has_its_hints_for_those_who_manage_projects(): void
    {
        $project = new Project();
        $project->name = 'Moths';
        $project->user_id = 1;
        $project->active = 1;
        $project->field_view_default = 'all';
        $project->save();

        $html = $this->as('admin')->get('/projects/' . $project->id . '/edit')->assertOk()->getContent();

        foreach (['project_field_view', 'project_sync_identifier', 'project_others_panel', 'project_sync_overwrite', 'project_managers'] as $key) {
            $this->assertStringContainsString(e(config("help.hints.$key.text")), $html, $key);
        }
    }

    public function test_the_validated_values_table_explains_its_status_column(): void
    {
        $html = $this->as('admin')->get('/validated')->assertOk()->getContent();

        $this->assertStringContainsString(e(config('help.hints.validated_status.text')), $html);
    }

    // ---- who gets the link ----------------------------------------------------------------------------------

    public function test_the_link_is_only_offered_to_those_who_may_read_the_guide(): void
    {
        $manager = $this->person('project_manager');
        $admin = $this->person('admin');

        $this->assertTrue(HelpController::canRead('administration', $manager));
        $this->assertFalse(HelpController::canRead('configuration', $manager));
        $this->assertTrue(HelpController::canRead('configuration', $admin));
        $this->assertFalse(HelpController::canRead('installation', $admin), 'guides that are not in the application cannot be linked');

        // a manager still gets the explanation of an administration hint, with its link
        Auth::login($manager);
        $this->assertStringContainsString('help-hint-more', help_hint('project_managers', 'en'));
        // and the text of a hint of the configuration guide, without a link
        $text = help_hint('settings_access', 'en');
        $this->assertStringContainsString(e(config('help.hints.settings_access.text')), $text);
        $this->assertStringNotContainsString('help-hint-more', $text);
    }

    public function test_a_visitor_gets_no_hint(): void
    {
        Auth::logout();

        $this->assertSame('', help_hint('field_type', 'en'));
        $this->assertSame('', help_hint('a_hint_that_does_not_exist', 'en'));
    }
}
