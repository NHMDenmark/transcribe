<?php

namespace App\Console\Commands;

use App\Services\ProjectStats;
use Illuminate\Console\Command;

class UpdateProjectStats extends Command
{
    protected $signature = 'platform:update-project-stats';

    protected $description = 'Recalculate the progress figures of every project (scheduled every night)';

    public function handle(ProjectStats $stats): int
    {
        $stats->update();
        $this->components->info('Project figures updated.');

        return self::SUCCESS;
    }
}
