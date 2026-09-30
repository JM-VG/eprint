<?php

namespace App\Console\Commands;

use App\Services\FileRetentionService;
use Illuminate\Console\Command;

class CleanupOldFiles extends Command
{
    protected $signature = 'files:cleanup';

    protected $description = 'Delete uploaded files of finished jobs older than retention_days, keeping the job records';

    public function handle(FileRetentionService $retention): int
    {
        $count = $retention->cleanup();

        $this->info("Removed files for {$count} job(s).");

        return self::SUCCESS;
    }
}
