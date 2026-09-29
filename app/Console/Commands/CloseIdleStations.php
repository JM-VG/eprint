<?php

namespace App\Console\Commands;

use App\Services\AvailabilityService;
use Illuminate\Console\Command;

class CloseIdleStations extends Command
{
    protected $signature = 'stations:close-idle';

    protected $description = 'Close station sessions with no heartbeat for more than 5 minutes (close_reason timeout)';

    public function handle(AvailabilityService $availability): int
    {
        $closed = $availability->closeIdle();

        $this->info("Closed {$closed} idle station session(s).");

        return self::SUCCESS;
    }
}
