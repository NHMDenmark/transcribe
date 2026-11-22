<?php

namespace App\Console;

use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Everything the platform does by itself runs from here, driven by one cron line (docs/installation.md):
        //   * * * * * cd /path/to/the/application && php artisan schedule:run
        // Times are in the application's time zone (UTC).

        // so that the Settings page can tell when the cron line is missing
        $schedule->call(fn () => Setting::markSchedulerSeen())->name('scheduler-heartbeat')->everyMinute();

        // the ranking of last week; daily, because it writes the week only once and so also catches up after a stop
        $schedule->command('platform:weekly-report')->dailyAt('00:10');

        // the automatic sync with the collection database: each project on its own schedule (Settings and the project form)
        $schedule->command('platform:sync-due')->everyMinute()->withoutOverlapping(180);

        // safety nets: these figures are also updated when something changes
        $schedule->command('platform:update-project-stats')->dailyAt('02:00');
        $schedule->command('platform:recount-users')->dailyAt('02:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
