<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Setting;
use App\Services\AutoSyncSchedule;
use App\Services\SyncRunner;
use Illuminate\Console\Command;

class SyncDue extends Command
{
    protected $signature = 'platform:sync-due
        {--project= : sync this project now, whatever its schedule (and the switches), and say what happened}';

    protected $description = 'Send the validated values of the projects whose automatic sync is due to the collection database (scheduled every minute)';

    public function handle(AutoSyncSchedule $schedule, SyncRunner $runner): int
    {
        if ($this->option('project')) {
            return $this->now($runner, (int) $this->option('project'));
        }

        $setting = Setting::current();
        if (! $setting->auto_sync) {
            return self::SUCCESS;       // the master switch is off: nothing to do, and nothing to say every minute
        }

        $now = now();
        foreach (Project::where('auto_sync', true)->orderBy('id')->get() as $project) {
            if (! $schedule->isDue($project, $now, $setting->schedule_timezone)) {
                continue;
            }

            // the slot is marked before the run, so a run that is cut short is not started again every minute
            $project->auto_sync_last_slot = $schedule->latestSlot($project, $now, $setting->schedule_timezone);
            $project->save();

            $run = $runner->run($project, 'auto');
            $this->line("{$project->name}: {$run->message}");
        }

        return self::SUCCESS;
    }

    private function now(SyncRunner $runner, int $id): int
    {
        $project = Project::find($id);
        if (! $project) {
            $this->components->error("There is no project $id.");

            return self::FAILURE;
        }

        $run = $runner->run($project, 'command');
        $this->line("{$project->name}: {$run->message}");

        return $run->failed() ? self::FAILURE : self::SUCCESS;
    }
}
