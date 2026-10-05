<?php

namespace App\Console\Commands;

use App\Firebase\DatabaseClient;
use Illuminate\Console\Command;
use Throwable;

class FirebasePing extends Command
{
    protected $signature = 'firebase:ping {--write : Write, read back and clean up a test value}';

    protected $description = 'Verify the Firebase Realtime Database connection';

    public function handle(DatabaseClient $database): int
    {
        $this->line('Driver: '.class_basename($database));

        try {
            if ($this->option('write')) {
                $path = 'healthcheck/'.now()->format('YmdHis');

                $database->set($path, ['at' => now()->toIso8601String()]);
                $value = $database->get($path);
                $database->remove('healthcheck');

                $this->line('Wrote and read back: '.json_encode($value));
            } else {
                $this->line('Read counters: '.json_encode($database->get('counters')));
            }
        } catch (Throwable $e) {
            $this->error('Firebase connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Firebase connection OK.');

        return self::SUCCESS;
    }
}
