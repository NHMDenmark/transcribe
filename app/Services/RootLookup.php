<?php

namespace App\Services;

/**
 * How the record to sync into is looked up in the collection database from the specimen id of the platform.
 *
 * A catalog number is a string, and how many zeros it starts with is a decision of each collection ("264",
 * "00000264", "000000264"). So a numeric specimen id is not turned into one fixed width: the lookup asks for every
 * width a number can reasonably have, in one request (`catalognumber__in=…`, which uses the index of the column).
 * More than one answer is never guessed at: it is told apart by the collection of the project, or refused.
 */
class RootLookup
{
    /** the widest catalog number tried: 1 to 12 digits */
    private const MAX_WIDTH = 12;

    /** The specimen id as it is matched: exactly as the platform holds it, without blanks. */
    public static function value($specimenId): string
    {
        return trim((string) $specimenId);
    }

    /** The query string of the lookup of $column = $value. */
    public static function query(string $column, string $value): string
    {
        if ($column === 'catalognumber' && $value !== '' && ctype_digit($value)) {
            return 'catalognumber__in=' . implode(',', self::candidates($value));
        }

        return $column . '=' . urlencode($value);
    }

    /** @return string[] the number, written with 0 to many leading zeros up to 12 digits */
    public static function candidates(string $digits): array
    {
        $number = ltrim($digits, '0');
        $number = $number === '' ? '0' : $number;

        $candidates = [$digits];
        for ($width = strlen($number); $width <= max(self::MAX_WIDTH, strlen($number)); $width++) {
            $candidates[] = str_pad($number, $width, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique($candidates));
    }

    /**
     * The record among what the lookup returned.
     *
     * @param  array<int,array>  $objects
     * @param  string|null  $collection  the id of the collection of the project, if it has one
     * @return array{0: ?array, 1: ?string} the record, or null with the reason why there is none to choose
     */
    public static function pick(string $column, array $objects, ?string $collection): array
    {
        if (count($objects) <= 1 || $column !== 'catalognumber') {
            return [$objects[0] ?? null, null];
        }

        if ($collection !== null && $collection !== '') {
            $inCollection = array_values(array_filter($objects, fn ($o) => (string) ($o['collectionmemberid'] ?? '') === $collection
                || str_ends_with((string) ($o['collection'] ?? ''), '/collection/' . $collection . '/')));
            if (count($inCollection) === 1) {
                return [$inCollection[0], null];
            }
        }

        $where = implode(', ', array_map(fn ($o) => ($o['catalognumber'] ?? '?') . ' ' . ($o['resource_uri'] ?? ''), array_slice($objects, 0, 4)));

        return [null, count($objects) . ' records have that catalog number (' . $where . '): set the collection of the project to choose between them.'];
    }
}
