<?php

namespace App\Services;

/**
 * Which entry of a list a line of a multi-value field belongs to.
 *
 * A record can hold a list of dependent objects (collectors, determinations, preparations, other identifiers). A value
 * submitted as "line N" must update the entry that line already is, and add one when it is not there yet.
 *
 * - Tables that have an `ordernumber` (collectors): the entry whose ordernumber is N. Specify's own position in the
 *   list is not stable (it shifts when entries are added or removed), so it is never used.
 * - Tables that have none (determinations, preparations, other identifiers...): the entry in position N of the list
 *   as Specify returns it (line 1 is the first). A line beyond the end adds an entry after the last one.
 */
class ListLine
{
    /** Whether the entries of $list are told apart by their ordernumber. */
    public static function isOrdered(array $list, array $item): bool
    {
        if (array_key_exists('ordernumber', $item)) {
            return true;
        }
        foreach ($list as $candidate) {
            if (is_array($candidate) && array_key_exists('ordernumber', $candidate)) {
                return true;
            }
        }

        return false;
    }

    /** The key in $list of the entry that line $position is, or null when the list has no such entry yet. */
    public static function find(array $list, array $item, int $position)
    {
        if (self::isOrdered($list, $item)) {
            foreach ($list as $key => $candidate) {
                if (is_array($candidate) && (int) ($candidate['ordernumber'] ?? -1) === $position) {
                    return $key;
                }
            }

            return null;
        }

        $keys = array_keys($list);
        $key = $keys[max($position, 1) - 1] ?? null;

        return $key !== null && is_array($list[$key]) ? $key : null;
    }
}
