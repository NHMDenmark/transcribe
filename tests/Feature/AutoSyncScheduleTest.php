<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Services\AutoSyncSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When a project's automatic sync is due: the shapes of a schedule, in the time zone of the installation.
 */
class AutoSyncScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $attributes): Project
    {
        $project = new Project();
        $project->forceFill(['auto_sync' => true, 'auto_sync_frequency' => 'daily', 'auto_sync_time' => '02:00', 'auto_sync_weekday' => 1, 'auto_sync_every' => 6] + []);
        $project->forceFill($attributes);

        return $project;
    }

    private function slot(array $attributes, string $now, string $zone = 'UTC', bool $next = false): string
    {
        $schedule = new AutoSyncSchedule();
        $project = $this->project($attributes);
        $moment = Carbon::parse($now, 'UTC');

        return ($next ? $schedule->nextSlot($project, $moment, $zone) : $schedule->latestSlot($project, $moment, $zone))->format('Y-m-d H:i');
    }

    public function test_every_hour_is_the_start_of_the_hour(): void
    {
        $this->assertSame('2026-11-09 10:00', $this->slot(['auto_sync_frequency' => 'hourly'], '2026-11-09 10:42:17'));
        $this->assertSame('2026-11-09 11:00', $this->slot(['auto_sync_frequency' => 'hourly'], '2026-11-09 10:42:17', 'UTC', true));
    }

    public function test_every_few_hours_lines_up_with_midnight(): void
    {
        $six = ['auto_sync_frequency' => 'every_n_hours', 'auto_sync_every' => 6];
        $this->assertSame('2026-11-09 06:00', $this->slot($six, '2026-11-09 11:59'));
        $this->assertSame('2026-11-09 12:00', $this->slot($six, '2026-11-09 12:00'));
        $this->assertSame('2026-11-09 18:00', $this->slot($six, '2026-11-09 12:00', 'UTC', true));
        $this->assertSame('2026-11-09 00:00', $this->slot($six, '2026-11-09 05:59'));
        // a number that is not on the list falls back to six
        $this->assertSame('2026-11-09 06:00', $this->slot(['auto_sync_frequency' => 'every_n_hours', 'auto_sync_every' => 7], '2026-11-09 11:59'));
    }

    public function test_every_day_at_a_time_before_or_after_that_time(): void
    {
        $daily = ['auto_sync_frequency' => 'daily', 'auto_sync_time' => '02:00'];
        $this->assertSame('2026-11-09 02:00', $this->slot($daily, '2026-11-09 02:00'));
        $this->assertSame('2026-11-09 02:00', $this->slot($daily, '2026-11-09 15:00'));
        $this->assertSame('2026-11-08 02:00', $this->slot($daily, '2026-11-09 01:59'), 'not yet today: the last one was yesterday');
        $this->assertSame('2026-11-10 02:00', $this->slot($daily, '2026-11-09 15:00', 'UTC', true));
    }

    public function test_every_week_on_a_day_and_a_time(): void
    {
        $monday = ['auto_sync_frequency' => 'weekly', 'auto_sync_weekday' => 1, 'auto_sync_time' => '03:30'];
        // Wednesday 11 November 2026: the Monday was the 9th
        $this->assertSame('2026-11-09 03:30', $this->slot($monday, '2026-11-11 12:00'));
        $this->assertSame('2026-11-16 03:30', $this->slot($monday, '2026-11-11 12:00', 'UTC', true));
        // Monday itself, before and after the time
        $this->assertSame('2026-11-02 03:30', $this->slot($monday, '2026-11-09 03:29'));
        $this->assertSame('2026-11-09 03:30', $this->slot($monday, '2026-11-09 03:30'));
        // Sunday as a day (0)
        $this->assertSame('2026-11-08 00:00', $this->slot(['auto_sync_frequency' => 'weekly', 'auto_sync_weekday' => 0, 'auto_sync_time' => '00:00'], '2026-11-11 12:00'));
    }

    public function test_the_time_is_the_time_in_the_zone_of_the_installation(): void
    {
        // Copenhagen is UTC+1 in November: 02:00 there is 01:00 UTC
        $daily = ['auto_sync_frequency' => 'daily', 'auto_sync_time' => '02:00'];
        $this->assertSame('2026-11-09 01:00', $this->slot($daily, '2026-11-09 12:00', 'Europe/Copenhagen'));
        // at 00:30 UTC (01:30 there) the last one was yesterday's
        $this->assertSame('2026-11-08 01:00', $this->slot($daily, '2026-11-09 00:30', 'Europe/Copenhagen'));
    }

    public function test_a_project_is_due_when_a_later_moment_has_come_than_the_one_it_handled(): void
    {
        $schedule = new AutoSyncSchedule();
        $now = Carbon::parse('2026-11-09 15:00', 'UTC');
        $daily = ['auto_sync_frequency' => 'daily', 'auto_sync_time' => '02:00'];

        // never handled any: due
        $this->assertTrue($schedule->isDue($this->project($daily), $now, 'UTC'));
        // handled today's: not due
        $this->assertFalse($schedule->isDue($this->project($daily + ['auto_sync_last_slot' => Carbon::parse('2026-11-09 02:00', 'UTC')]), $now, 'UTC'));
        // handled yesterday's: due (and once, whatever number of days were missed)
        $this->assertTrue($schedule->isDue($this->project($daily + ['auto_sync_last_slot' => Carbon::parse('2026-11-05 02:00', 'UTC')]), $now, 'UTC'));
        // switched off: never
        $this->assertFalse($schedule->isDue($this->project($daily + ['auto_sync' => false]), $now, 'UTC'));
    }

    public function test_it_describes_itself(): void
    {
        $schedule = new AutoSyncSchedule();

        $this->assertSame('every hour', $schedule->describe($this->project(['auto_sync_frequency' => 'hourly'])));
        $this->assertSame('every 4 hours', $schedule->describe($this->project(['auto_sync_frequency' => 'every_n_hours', 'auto_sync_every' => 4])));
        $this->assertSame('every day at 02:00', $schedule->describe($this->project([])));
        $this->assertSame('every Friday at 18:15', $schedule->describe($this->project(['auto_sync_frequency' => 'weekly', 'auto_sync_weekday' => 5, 'auto_sync_time' => '18:15'])));
    }
}
