<?php

namespace App\Services;

use App\Models\Dialogs;

/**
 * Single source of truth for "all dialog texts for a language", replacing
 * two things that used to each fetch it separately:
 *
 * - AppServiceProvider's $dialogs/$dialogs_html shared view variables (used
 *   directly in older Blade templates, e.g. $dialogs['Projects']).
 * - get_dialogs()/get_dialogs_simple()/get_dialogs_html() in app/helpers.php,
 *   which each ran their own one-row query per call — on a page like the
 *   specimen transcription form (44 call sites, inside per-field/per-line
 *   loops), that's 100+ near-identical queries per request, on top of the
 *   one bulk query AppServiceProvider already ran for the same data.
 *
 * Bound as a singleton (see AppServiceProvider::register()) so every caller
 * within one request/app-container shares the same memoized result per
 * language — resolved lazily, so a request that never touches dialog text
 * (a JSON endpoint, say) still never queries at all.
 */
class DialogTextRepository
{
    /** @var array<string, array{0: array<string, string>, 1: array<string, string>}> */
    private array $cache = [];

    /**
     * Returns [plain, html] arrays keyed by dialog identifier for $language —
     * plain has tags stripped (what get_dialogs()/$dialogs render), html
     * keeps the stored markup (what get_dialogs_html()/$dialogs_html render).
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public function forLanguage(string $language): array
    {
        if (!isset($this->cache[$language])) {
            $plain = [];
            $html = [];

            foreach (Dialogs::where('language', $language)->get() as $dialog) {
                $html[$dialog->identifier] = $dialog->text;
                $plain[$dialog->identifier] = strip_tags($dialog->text);
            }

            $this->cache[$language] = [$plain, $html];
        }

        return $this->cache[$language];
    }
}
