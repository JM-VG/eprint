<?php

namespace App\Console\Commands;

use App\Services\LegacySalesImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportLegacySales extends Command
{
    protected $signature = 'sales:import-legacy {file : Path to the CSV export from the old system} {--dry-run : Validate only, save nothing}';

    protected $description = 'One-time import of legacy walk-in sales from CSV (all rows must be valid)';

    public function handle(LegacySalesImporter $importer): int
    {
        try {
            ['rows' => $rows, 'errors' => $errors] = $importer->read($this->argument('file'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($errors) {
            foreach ($errors as $line => $messages) {
                $this->error("Line {$line}: ".implode(' ', $messages));
            }
            $this->error('Nothing was imported. Fix the rows above and run again.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: '.count($rows).' valid row(s). Nothing was saved.');

            return self::SUCCESS;
        }

        ['imported' => $imported, 'skipped' => $skipped] = $importer->import($rows);

        $this->info("Imported {$imported} sale(s). Skipped {$skipped} duplicate(s).");

        return self::SUCCESS;
    }
}
