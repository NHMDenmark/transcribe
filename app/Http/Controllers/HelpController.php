<?php

namespace App\Http\Controllers;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * The guides in docs/ shown inside the application, so the help travels with the software and is always the
 * version that matches it. Only the guides meant for people who use the platform are listed here: the
 * installation and development guides are about servers and code and stay in the repository.
 */
class HelpController extends Controller
{
    /** page => [file, roles that may read it (empty: every signed-in person), title] */
    private const PAGES = [
        'transcribing' => ['transcribing.md', [], 'Transcribing'],
        'administration' => ['administration.md', ['root', 'admin', 'project_manager'], 'Administration'],
        'configuration' => ['configuration.md', ['root', 'admin'], 'Configuration'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(?string $page = null)
    {
        $pages = $this->pagesFor(auth()->user());
        $page ??= array_key_first($pages);
        abort_unless(isset($pages[$page]), 404);

        return view('help.show', [
            'pages' => $pages,
            'current' => $page,
            'html' => $this->render(self::PAGES[$page][0]),
        ]);
    }

    /** Whether this person may read the guide (the "?" hints link to it only for those who can). */
    public static function canRead(string $guide, $user): bool
    {
        if (! isset(self::PAGES[$guide])) {
            return false;
        }
        $roles = self::PAGES[$guide][1];

        return $roles === [] || $user->hasRole(...$roles);
    }

    /** @return array<string,string> page => title, only what this person may read */
    private function pagesFor($user): array
    {
        $out = [];
        foreach (self::PAGES as $key => [, $roles, $title]) {
            if ($roles === [] || $user->hasRole(...$roles)) {
                $out[$key] = $title;
            }
        }

        return $out;
    }

    private function render(string $file): string
    {
        $markdown = (string) file_get_contents(base_path('docs/' . $file));

        // links between guides become links between help pages; links to what is not inside the application become plain text
        $markdown = preg_replace('/\]\((transcribing|administration|configuration)\.md(#[^)]*)?\)/', '](' . url('help') . '/$1$2)', $markdown);
        $markdown = preg_replace('/\[([^\]]+)\]\((?:installation\.md|development\.md|\.\.\/CHANGELOG\.md)(?:#[^)]*)?\)/', '$1', $markdown);

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // ids on the headings, so that the list at the top of a guide and the links between guides can point at them
            'heading_permalink' => ['symbol' => '', 'html_class' => 'help-anchor', 'insert' => 'after', 'aria_hidden' => true, 'id_prefix' => '', 'fragment_prefix' => ''],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());   // tables
        $environment->addExtension(new HeadingPermalinkExtension());

        $html = (string) (new MarkdownConverter($environment))->convert($markdown);

        return str_replace('<table>', '<table class="table table-sm table-bordered align-middle">', $html);
    }
}
