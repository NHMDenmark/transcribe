<?php

namespace App\Console\Commands;

use App\Services\WeeklyReport;
use Illuminate\Console\Command;

class WeeklyReportCommand extends Command
{
    protected $signature = 'platform:weekly-report';

    protected $description = 'Write the ranking of contributors for the last week, once (scheduled daily; running it again changes nothing)';

    public function handle(WeeklyReport $report): int
    {
        [$start, , $label] = $report->lastWeek();
        $written = $report->generate();

        $this->components->info($written > 0
            ? "Week $label (from {$start->toDateString()}): $written contributors written."
            : "Week $label is already written.");

        return self::SUCCESS;
    }
}
