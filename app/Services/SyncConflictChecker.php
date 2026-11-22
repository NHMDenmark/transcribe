<?php

namespace App\Services;

use App\Models\Project;

/**
 * Compares the delta a sync is about to write against the record as it
 * currently exists in Specify, part by part. Empty or equal parts go through;
 * a part that holds a different value (or, for picks, a link to a different
 * record) is removed from the delta and reported as a conflict.
 */
class SyncConflictChecker
{
    /** @var callable|null fn(string $uri): ?array  loads a related record that came back as a bare URI */
    private $fetch;

    public function __construct(
        private bool $caseInsensitive = false,
        private ?int $roundDecimals = null,
        ?callable $fetch = null
    ) {
        $this->fetch = $fetch;
    }

    public static function forProject(Project $project, ?callable $fetch = null): self
    {
        return new self(
            (bool) $project->sync_compare_case_insensitive,
            $project->sync_compare_round_decimals !== null ? (int) $project->sync_compare_round_decimals : null,
            $fetch
        );
    }

    /**
     * @return array{delta: array, conflicts: array, decisions: array}
     *   conflicts/decisions entries: path, kind (write|pick), existing, proposed, decision
     */
    public function check(array $delta, array $existing, int $position): array
    {
        $conflicts = [];
        $decisions = [];
        $filtered = $this->walk($delta, $existing, $position, '', $conflicts, $decisions);

        return ['delta' => $filtered, 'conflicts' => $conflicts, 'decisions' => $decisions];
    }

    private function walk(array $delta, array $existing, int $position, string $path, array &$conflicts, array &$decisions): array
    {
        $out = [];

        foreach ($delta as $key => $value) {
            $here = $path === '' ? (string) $key : $path . '.' . $key;

            if ($key === 'ordernumber') {
                $out[$key] = $value;
                continue;
            }

            // Companion of a date leaf (e.g. startdateprecision): follows its date, added back below.
            if (is_string($key) && str_ends_with($key, 'precision') && !is_array($value)
                && array_key_exists(substr($key, 0, -9), $delta)) {
                continue;
            }

            if (is_array($value) && $this->isList($value)) {
                $existingList = isset($existing[$key]) && is_array($existing[$key]) ? $existing[$key] : [];
                $items = [];
                foreach ($value as $item) {
                    $line  = ListLine::find($existingList, $item, $position);
                    $match = $line !== null && is_array($existingList[$line]) ? $existingList[$line] : [];
                    $sub = $this->walk($item, $match, $position, $here . '[' . $position . ']', $conflicts, $decisions);
                    if (count(array_diff(array_keys($sub), ['ordernumber'])) > 0) {
                        $items[] = $sub;
                    }
                }
                if ($items) {
                    $out[$key] = $items;
                }
                continue;
            }

            if (is_array($value)) {
                $child = $existing[$key] ?? null;
                if (is_string($child) && $this->isUri($child) && $this->fetch) {
                    $child = ($this->fetch)($child);
                }
                $sub = $this->walk($value, is_array($child) ? $child : [], $position, $here, $conflicts, $decisions);
                if ($sub) {
                    $out[$key] = $sub;
                }
                continue;
            }

            $isPick = is_string($value) && $this->isUri($value);
            $current = $existing[$key] ?? null;
            if ($isPick && is_array($current)) {
                $current = $current['resource_uri'] ?? null;
            }

            $entry = [
                'path' => $here,
                'kind' => $isPick ? 'pick' : 'write',
                'existing' => is_scalar($current) ? (string) $current : null,
                'proposed' => is_scalar($value) ? (string) $value : null,
            ];

            if ($this->isEmpty($current)) {
                $decisions[] = $entry + ['decision' => 'write'];
                $out[$key] = $value;
            } elseif ($this->same($current, $value, $isPick)) {
                $decisions[] = $entry + ['decision' => 'equal'];
                $out[$key] = $value;
            } else {
                $decisions[] = $entry + ['decision' => 'conflict'];
                $conflicts[] = $entry + ['decision' => 'conflict'];
            }
        }

        // A date's precision companion goes out only if the date itself does.
        foreach ($delta as $key => $value) {
            $base = is_string($key) && str_ends_with($key, 'precision') ? substr($key, 0, -9) : null;
            if ($base !== null && !is_array($value) && array_key_exists($base, $delta) && array_key_exists($base, $out)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function isList(array $a): bool
    {
        return $a !== [] && array_keys($a) === range(0, count($a) - 1);
    }

    private function isUri(string $s): bool
    {
        return str_starts_with($s, '/api/specify/');
    }

    private function isEmpty(mixed $v): bool
    {
        return $v === null || (is_string($v) && trim($v) === '') || $v === [];
    }

    /** Whether two values count as equal under this project's comparison rules. */
    public function same(mixed $existing, mixed $proposed, bool $isPick = false): bool
    {
        $a = trim((string) $existing);
        $b = trim((string) $proposed);

        if ($isPick) {
            return rtrim($a, '/') === rtrim($b, '/');
        }

        if ($this->caseInsensitive) {
            $a = mb_strtolower($a);
            $b = mb_strtolower($b);
        }

        // "55.1234000000" is the same coordinate as "55.1234"; plain integers stay textual ("007" != "7").
        if (is_numeric($a) && is_numeric($b) && ($this->roundDecimals !== null || str_contains($a, '.') || str_contains($b, '.'))) {
            if ($this->roundDecimals !== null) {
                return round((float) $a, $this->roundDecimals) === round((float) $b, $this->roundDecimals);
            }
            return abs((float) $a - (float) $b) < 1e-9;
        }

        return $a === $b;
    }
}
