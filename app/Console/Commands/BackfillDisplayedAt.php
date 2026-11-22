<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDisplayedAt extends Command
{
    protected $signature = 'app:backfill-displayed-at {--max-gap=30 : Ignore gaps longer than this many minutes (breaks, not transcription)} {--dry-run : Report what would change without writing}';

    protected $description = 'Estimate displayed_at for old submissions as the end of the same user\'s previous submission';

    // Rows of one form submit are saved one after another; this groups them.
    private const SAME_SUBMIT_SECONDS = 5;

    public function handle(): int
    {
        $maxGap = (int) $this->option('max-gap') * 60;
        $dry = (bool) $this->option('dry-run');

        $filled = 0;
        $clustersFilled = 0;
        $tooLongOrFirst = 0;
        $durations = [];

        $users = DB::table('collaborative_fields')->distinct()->pluck('user_id');

        foreach ($users as $userId) {
            $clusters = $this->clusters($userId);
            $prevEnd = null;

            DB::transaction(function () use ($clusters, $maxGap, $dry, &$prevEnd, &$filled, &$clustersFilled, &$tooLongOrFirst, &$durations) {
                foreach ($clusters as $cluster) {
                    $gap = $prevEnd === null ? null : $cluster['start']->diffInSeconds($prevEnd);
                    $estimable = $gap !== null && $gap > 0 && $gap <= $maxGap;

                    if ($estimable) {
                        $pending = array_values($cluster['pending']);
                        if ($pending) {
                            $clustersFilled++;
                            $durations[] = $gap;
                            $filled += count($pending);
                            if (!$dry) {
                                foreach (array_chunk($pending, 500) as $ids) {
                                    DB::table('collaborative_fields')->whereIn('id', $ids)->update([
                                        'displayed_at' => $prevEnd->toDateTimeString(),
                                    ]);
                                }
                            }
                        }
                    } elseif ($cluster['pending']) {
                        $tooLongOrFirst++;
                    }

                    $prevEnd = $cluster['end'];
                }
            });
        }

        sort($durations);
        $median = $durations ? $durations[intdiv(count($durations), 2)] : 0;

        $this->info(($dry ? '[dry run] would fill' : 'Filled') . " {$filled} rows in {$clustersFilled} submissions (median {$median}s).");
        $this->line("Left empty: {$tooLongOrFirst} submissions (first of the user, or gap over " . $this->option('max-gap') . ' min).');

        return self::SUCCESS;
    }

    /** One user's submissions in time order: start/end of each, plus ids still missing displayed_at. */
    private function clusters($userId): \Generator
    {
        $current = null;

        $rows = DB::table('collaborative_fields')
            ->where('user_id', $userId)
            ->whereNotNull('created_at')
            ->orderBy('created_at')->orderBy('id')
            ->select('id', 'specimen_id', 'created_at', 'displayed_at')
            ->cursor();

        foreach ($rows as $row) {
            $at = Carbon::parse($row->created_at);

            if ($current && $current['specimen'] === $row->specimen_id && $at->diffInSeconds($current['end']) <= self::SAME_SUBMIT_SECONDS) {
                $current['end'] = $at;
            } else {
                if ($current) {
                    yield $current;
                }
                $current = ['specimen' => $row->specimen_id, 'start' => $at, 'end' => $at, 'pending' => []];
            }

            if ($row->displayed_at === null) {
                $current['pending'][] = $row->id;
            }
        }

        if ($current) {
            yield $current;
        }
    }
}
