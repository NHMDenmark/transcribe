<?php

namespace App\Services;

use App\Models\Project;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * When a project's automatic sync is due. A schedule is a few fixed shapes (an hour, every few hours, a time of day, a
 * day of the week and a time) rather than free cron text, written in the time zone chosen in Settings. The scheduler
 * calls this every minute: a project is due when a scheduled moment ("slot") has passed that it has not handled yet.
 * Slots are remembered (auto_sync_last_slot), so a stop of the server means one catch-up run, not one per missed slot.
 */
class AutoSyncSchedule
{
    public const FREQUENCIES = ['hourly', 'every_n_hours', 'daily', 'weekly'];

    /** Divisors of 24, so that the hours line up with midnight every day. */
    public const EVERY = [2, 3, 4, 6, 8, 12];

    /** The latest scheduled moment that is not in the future (as an instant). */
    public function latestSlot(Project $project, CarbonInterface $now, string $timezone): Carbon
    {
        return $this->slot($project, Carbon::instance($now)->setTimezone($timezone), false);
    }

    /** The first scheduled moment after $now. */
    public function nextSlot(Project $project, CarbonInterface $now, string $timezone): Carbon
    {
        return $this->slot($project, Carbon::instance($now)->setTimezone($timezone), true);
    }

    public function isDue(Project $project, CarbonInterface $now, string $timezone): bool
    {
        if (! $project->auto_sync) {
            return false;
        }
        $last = $project->auto_sync_last_slot;

        return $last === null || $this->latestSlot($project, $now, $timezone)->greaterThan(Carbon::parse($last, 'UTC'));
    }

    /** "every day at 02:00", for lists and logs. */
    public function describe(Project $project): string
    {
        return match ($project->auto_sync_frequency) {
            'hourly' => 'every hour',
            'every_n_hours' => 'every ' . (int) $project->auto_sync_every . ' hours',
            'weekly' => 'every ' . $this->weekday((int) $project->auto_sync_weekday) . ' at ' . $project->auto_sync_time,
            default => 'every day at ' . $project->auto_sync_time,
        };
    }

    public function weekday(int $day): string
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day % 7];
    }

    private function slot(Project $project, Carbon $local, bool $next): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $project->auto_sync_time ?: '00:00') + [0, 0]);

        switch ($project->auto_sync_frequency) {
            case 'hourly':
                $slot = $local->copy()->startOfHour();
                $step = fn (Carbon $t) => $t->addHour();
                break;

            case 'every_n_hours':
                $every = in_array((int) $project->auto_sync_every, self::EVERY, true) ? (int) $project->auto_sync_every : 6;
                $slot = $local->copy()->startOfHour();
                $slot->setTime(intdiv($slot->hour, $every) * $every, 0);
                $step = fn (Carbon $t) => $t->addHours($every);
                break;

            case 'weekly':
                $back = ($local->dayOfWeek - (int) $project->auto_sync_weekday + 7) % 7;
                $slot = $local->copy()->subDays($back)->setTime($hour, $minute);
                if ($slot->greaterThan($local)) {
                    $slot->subWeek();
                }
                $step = fn (Carbon $t) => $t->addWeek();
                break;

            default:
                $slot = $local->copy()->setTime($hour, $minute);
                if ($slot->greaterThan($local)) {
                    $slot->subDay();
                }
                $step = fn (Carbon $t) => $t->addDay();
        }

        if ($next) {
            $slot = $step($slot);
        }

        return $slot->setTimezone('UTC');
    }
}
