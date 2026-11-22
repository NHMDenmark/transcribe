<?php

namespace App\Services;

use App\Models\Collaborative_fields;
use App\Models\User;
use App\Models\Week_report;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The weekly ranking of contributors. A week runs from Sunday 00:00 to the next Sunday 00:00 and is named after the
 * year and ISO week of its Saturday ("2026_45"). The report of a week is written once, after it ended: running it
 * again changes nothing that is already there (and a person who has no row yet gets one).
 */
class WeeklyReport
{
    /** The last week that has ended, as [start, end, label]. */
    public function lastWeek(?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $start = $now->copy()->subDays(6)->startOfDay()->previous(Carbon::SUNDAY);

        return [$start, $start->copy()->addWeek(), $start->copy()->addDays(6)->format('Y_W')];
    }

    /** @return int how many rows were written */
    public function generate(?CarbonInterface $now = null): int
    {
        [$start, $end, $label] = $this->lastWeek($now);
        $written = 0;

        foreach (User::whereNotNull('email')->orderBy('id')->get() as $user) {
            if (Week_report::where('user_id', $user->id)->where('year_week', $label)->exists()) {
                continue;
            }

            $valid = $this->count(2, $user->id, $start, $end);
            $rejected = $this->count(3, $user->id, $start, $end);
            $submitted = $this->count(1, $user->id, $start, $end);

            $row = new Week_report();
            $row->user_id = $user->id;
            $row->year_week = $label;
            $row->submitted = $submitted;
            $row->validated = $valid;
            $row->rejected = $rejected;
            $row->total = $submitted + $valid + $rejected;
            $row->save();
            $written++;
        }

        return $written;
    }

    /** Whether the report of the last week is already there. */
    public function isDone(?CarbonInterface $now = null): bool
    {
        return Week_report::where('year_week', $this->lastWeek($now)[2])->exists();
    }

    private function count(int $status, int $userId, CarbonInterface $start, CarbonInterface $end): int
    {
        return Collaborative_fields::where('status', '=', $status)->groupBy('specimen_id', 'field_id')->where('user_id', '=', "$userId")
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)->get()->count();
    }
}
