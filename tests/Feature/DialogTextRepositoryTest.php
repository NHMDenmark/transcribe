<?php

namespace Tests\Feature;

use App\Models\Dialogs;
use App\Services\DialogTextRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the dialogs N+1 fix: get_dialogs()/
 * get_dialogs_simple()/get_dialogs_html() (app/helpers.php) and
 * AppServiceProvider's $dialogs/$dialogs_html view data used to each run
 * their own query per dialog/call — now they all share one memoized,
 * per-language fetch via App\Services\DialogTextRepository, bound as a
 * singleton so it resets cleanly between tests (a raw static cache on the
 * model would leak stale data across tests despite RefreshDatabase).
 */
class DialogTextRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeDialog(string $identifier, string $language, string $text): void
    {
        $dialog = new Dialogs();
        $dialog->identifier = $identifier;
        $dialog->language = $language;
        $dialog->text = $text;
        $dialog->save();
    }

    public function test_forLanguage_splits_plain_and_html_and_falls_back_gracefully(): void
    {
        $this->makeDialog('greeting', 'en', '<p>Hello</p>');
        $this->makeDialog('greeting', 'dk', '<p>Hej</p>');

        [$plain, $html] = app(DialogTextRepository::class)->forLanguage('en');

        $this->assertSame('Hello', $plain['greeting']);
        $this->assertSame('<p>Hello</p>', $html['greeting']);

        // the dk-only dialog must not leak into the en result
        $this->assertArrayNotHasKey('greeting_dk_only', $plain);
    }

    public function test_forLanguage_is_memoized_within_the_same_container(): void
    {
        $this->makeDialog('greeting', 'en', 'Hello');

        $repo = app(DialogTextRepository::class);
        $repo->forLanguage('en');

        DB::enableQueryLog();
        $repo->forLanguage('en'); // second call, same instance
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'a second call for the same language must not hit the database again');
    }

    public function test_get_dialogs_helpers_use_the_shared_repository(): void
    {
        $this->makeDialog('write_comment', 'en', '<b>Write comment</b>');

        $this->assertSame('Write comment', get_dialogs_simple('write_comment', 'en'));
        $this->assertStringStartsWith('Write comment', get_dialogs('write_comment', 'en'));
        $this->assertStringStartsWith('<b>Write comment</b>', get_dialogs_html('write_comment', 'en'));
    }

    public function test_get_dialogs_falls_back_to_the_identifier_when_missing(): void
    {
        $this->assertSame('no_such_dialog', get_dialogs_simple('no_such_dialog', 'en'));
    }
}
