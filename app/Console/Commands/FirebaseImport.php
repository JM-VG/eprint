<?php

namespace App\Console\Commands;

use App\Firebase\DataImporter;
use Illuminate\Console\Command;

class FirebaseImport extends Command
{
    protected $signature = 'firebase:import
        {--connection=mysql : The relational source connection}
        {--table=* : Import only these tables (default: all)}';

    protected $description = 'Copy data from a relational database into the Firebase Realtime Database';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $tables = array_values(array_filter((array) $this->option('table')));

        $this->info("Importing from [{$connection}] into Firebase...");

        try {
            $counts = (new DataImporter($connection))->import($tables);
        } catch (\Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-20s %d', $table, $count));
        }

        $this->info('Import complete.');

        return self::SUCCESS;
    }
}
