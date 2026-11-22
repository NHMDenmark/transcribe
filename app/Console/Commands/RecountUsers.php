<?php

namespace App\Console\Commands;

use App\Services\UserCounts;
use Illuminate\Console\Command;

class RecountUsers extends Command
{
    protected $signature = 'platform:recount-users';

    protected $description = 'Recount the submissions of every contributor (scheduled every night)';

    public function handle(UserCounts $counts): int
    {
        $counts->recount();
        $this->components->info('Contributor figures updated.');

        return self::SUCCESS;
    }
}
