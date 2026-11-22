<?php

namespace App\Services;

use App\Models\Dialogs;
use App\Models\Language;

/**
 * The texts of the menu and of the pages: one dialog per language, so they are translated, edited
 * and stored exactly like every other text of the platform. Unlike a label, a page text that
 * has not been written in the visitor's language falls back to the default language (and then to any
 * other) instead of showing its identifier.
 */
class ContentTexts
{
    public function __construct(private DialogTextRepository $dialogs)
    {
    }

    /** The stored HTML of an identifier in a language; empty if that language has none. */
    public function exact(string $identifier, string $language): string
    {
        return (string) ($this->dialogs->forLanguage($language)[1][$identifier] ?? '');
    }

    /** The text in the visitor's language, else the default language, else whichever exists. */
    public function get(string $identifier, string $language): string
    {
        $candidates = array_unique(array_merge(
            [$language, (string) config('app.fallback_locale', 'en')],
            Language::pluck('iso2')->all()
        ));

        foreach ($candidates as $candidate) {
            $text = $this->exact($identifier, $candidate);
            if (trim(strip_tags($text)) !== '') {
                return $text;
            }
        }

        return '';
    }

    /** @param array<string, string|null> $byLanguage iso2 => text */
    public function set(string $identifier, array $byLanguage): void
    {
        foreach ($byLanguage as $language => $text) {
            $row = Dialogs::where('identifier', $identifier)->where('language', $language)->first();
            if (!$row) {
                $row = new Dialogs();
                $row->identifier = $identifier;
                $row->language = $language;
            }
            $row->text = (string) $text;
            $row->save();
        }
    }

    public function forget(string ...$identifiers): void
    {
        Dialogs::whereIn('identifier', $identifiers)->delete();
    }

    /** iso2 => text for every language that has the identifier, for the editing forms. */
    public function all(string $identifier): array
    {
        return Dialogs::where('identifier', $identifier)->pluck('text', 'language')->all();
    }
}
