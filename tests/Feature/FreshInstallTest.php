<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A new installation, by an institution other than the one the software was written for: what the base data
 * contains, how the first administrator is created, and that nothing belonging to a client is left in the code.
 */
class FreshInstallTest extends TestCase
{
    use RefreshDatabase;

    // ---- the base data ----------------------------------------------------------------------------------

    public function test_the_base_data_gives_a_master_language_the_levels_and_the_texts(): void
    {
        $this->seed(BaseDataSeeder::class);

        $this->assertSame(1, DB::table('languages')->count());
        $this->assertSame(['en', 'English', 1], $this->languageRow());

        $this->assertSame(
            ['Validator', 'Basic', 'Beginner', 'Competent', 'Advanced', 'Expert'],
            DB::table('user_categories')->orderBy('id')->pluck('category')->all()
        );
        $this->assertSame(0, (int) DB::table('user_categories')->min('id'), 'the level of the validators has id 0');
        $this->assertNull(DB::table('user_categories')->where('id', 0)->value('validations_required'));

        $this->assertGreaterThan(15, DB::table('dialogs')->where('language', 'en')->count());
        $this->assertSame('Transcription platform', DB::table('dialogs')->where('identifier', 'platform_title')->where('language', 'en')->value('text'));
    }

    public function test_running_it_again_adds_nothing_and_changes_nothing(): void
    {
        $this->seed(BaseDataSeeder::class);
        DB::table('dialogs')->where('identifier', 'platform_title')->update(['text' => 'Our own title']);
        DB::table('user_categories')->where('id', 3)->update(['category' => 'Intermediate']);
        $counts = [DB::table('languages')->count(), DB::table('user_categories')->count(), DB::table('dialogs')->count()];

        $this->seed(BaseDataSeeder::class);

        $this->assertSame($counts, [DB::table('languages')->count(), DB::table('user_categories')->count(), DB::table('dialogs')->count()]);
        $this->assertSame('Our own title', DB::table('dialogs')->where('identifier', 'platform_title')->value('text'));
        $this->assertSame('Intermediate', DB::table('user_categories')->where('id', 3)->value('category'));
    }

    public function test_a_text_added_by_a_new_version_arrives_without_touching_the_others(): void
    {
        $this->seed(BaseDataSeeder::class);
        DB::table('dialogs')->where('identifier', 'skip')->delete();   // as if the version had not had it yet
        DB::table('dialogs')->where('identifier', 'submit')->update(['text' => 'Send']);

        $this->seed(BaseDataSeeder::class);

        $this->assertSame('Skip', DB::table('dialogs')->where('identifier', 'skip')->value('text'));
        $this->assertSame('Send', DB::table('dialogs')->where('identifier', 'submit')->value('text'));
    }

    public function test_an_installation_that_already_has_languages_and_levels_keeps_them(): void
    {
        $language = new Language();
        $language->iso2 = 'pt';
        $language->text = 'Português';
        $language->master = 1;
        $language->active = 1;
        $language->save();
        DB::table('user_categories')->insert(['category' => 'Mine', 'points' => 5, 'validations_required' => 0]);

        $this->seed(BaseDataSeeder::class);

        $this->assertSame(['pt'], DB::table('languages')->pluck('iso2')->all());
        $this->assertSame(['Mine'], DB::table('user_categories')->pluck('category')->all());
        // the texts go to the master language, whichever it is
        $this->assertSame(0, DB::table('dialogs')->where('language', 'en')->count());
        $this->assertGreaterThan(15, DB::table('dialogs')->where('language', 'pt')->count());
    }

    public function test_a_new_installation_has_no_logo_and_shows_the_name_of_the_application(): void
    {
        $this->seed(BaseDataSeeder::class);

        $this->assertNull(Setting::current()->logoUrl());
        $this->assertNull(Setting::current()->loadingGifUrl());

        // a new installation asks visitors to sign in: look at the home page as its first administrator
        $this->artisan('platform:install', ['--name' => 'Ada', '--email' => 'ada@example.test', '--password' => 'a-long-password']);
        $html = $this->actingAs(User::where('email', 'ada@example.test')->firstOrFail())->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('branding/', $html, 'no logo is shown');
        $this->assertStringContainsString(e(config('app.name')), $html, 'the name of the application is shown in its place');
        $this->assertStringContainsString('Transcription platform', $html);
    }

    // ---- the command ------------------------------------------------------------------------------------

    public function test_the_install_command_creates_the_first_administrator(): void
    {
        $this->artisan('platform:install', ['--name' => 'Ada Admin', '--email' => 'ada@example.test', '--password' => 'a-long-password'])
            ->assertExitCode(0);

        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertSame('root', $user->role);
        $this->assertSame(0, (int) $user->category);
        $this->assertNotNull($user->email_verified_at, 'the first administrator is not asked to confirm an e-mail');
        $this->assertTrue(Hash::check('a-long-password', $user->password));
        $this->assertSame(1, DB::table('languages')->count(), 'the base data came with it');
    }

    public function test_the_install_command_can_be_run_again_and_does_not_add_a_second_administrator(): void
    {
        $args = ['--name' => 'Ada Admin', '--email' => 'ada@example.test', '--password' => 'a-long-password'];
        $this->artisan('platform:install', $args)->assertExitCode(0);
        $this->artisan('platform:install', ['--name' => 'Other', '--email' => 'other@example.test', '--password' => 'another-password'])->assertExitCode(0);

        $this->assertSame(1, User::where('role', 'root')->count());
        $this->assertSame(1, DB::table('languages')->count());
        $this->assertSame(6, DB::table('user_categories')->count());
    }

    public function test_the_install_command_refuses_a_weak_or_missing_password(): void
    {
        $this->artisan('platform:install', ['--name' => 'Ada', '--email' => 'ada@example.test', '--password' => 'short', '--no-interaction' => true])
            ->assertExitCode(1);
        $this->assertSame(0, User::count());

        $this->artisan('platform:install', ['--no-interaction' => true])->assertExitCode(1);
        $this->assertSame(0, User::count());
    }

    public function test_the_install_command_can_leave_the_administrator_out(): void
    {
        $this->artisan('platform:install', ['--no-admin' => true])->assertExitCode(0);

        $this->assertSame(0, User::count());
        $this->assertSame(1, DB::table('languages')->count());
    }

    // ---- nothing of a client is left in the software --------------------------------------------------------

    public function test_the_software_carries_no_reference_to_a_client_or_to_a_server(): void
    {
        $forbidden = '/dassco|nhmd?\b|nhm\.dk|bhsi|ucloud|refinery|172\.22\.|\/work\/transcription|coicatalogue/i';
        $files = array_merge(
            [base_path('README.md'), base_path('.env.example'), base_path('.env.testing.example')],
            $this->files(base_path('docs'), ['md']),
            $this->files(base_path('app'), ['php']),
            $this->files(base_path('config'), ['php']),
            $this->files(base_path('routes'), ['php']),
            $this->files(base_path('resources/views'), ['php']),
            $this->files(base_path('resources/sass'), ['scss']),
            $this->files(base_path('database'), ['php'])
        );

        $found = [];
        foreach ($files as $file) {
            if (is_file($file) && preg_match_all($forbidden, (string) file_get_contents($file), $m)) {
                $found[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file) . ' (' . implode(', ', array_unique($m[0])) . ')';
            }
        }

        $this->assertSame([], $found, "A reference to a client, an institution or a server was found.\nThe software is meant to be installed by any institution: keep those out of the code and the documents.");
    }

    public function test_the_software_carries_no_password_key_or_address_of_a_machine(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^APP_KEY=\s*$/m', $example, 'a key in .env.example would be shared by every installation');
        foreach (['API_PASSWORD', 'DB_PASSWORD', 'MAIL_PASSWORD'] as $name) {
            $this->assertMatchesRegularExpression('/^' . $name . '=\s*$/m', $example, $name . ' must be empty in the example');
        }
        $this->assertDoesNotMatchRegularExpression('/^#?\s*API_(USERNAME|PASSWORD|URL)=\S+/m', str_replace('API_USERNAME=transcription', '', $example), 'no commented credentials or addresses');
    }

    // ---- helpers ----------------------------------------------------------------------------------------

    private function languageRow(): array
    {
        $row = DB::table('languages')->first();

        return [$row->iso2, $row->text, (int) $row->master];
    }

    private function files(string $dir, array $extensions): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array(strtolower($file->getExtension()), $extensions, true)) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}
