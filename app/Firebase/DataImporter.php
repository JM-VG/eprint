<?php

namespace App\Firebase;

use App\Realtime\Database\TableSchema;
use App\Realtime\Database\Values;
use App\Repositories\Repository;
use App\Repositories\RepositoryRegistry;
use App\Repositories\SettingRepository;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill of the Firebase Realtime Database from the old
 * relational database (MySQL, or SQLite for local copies). Ids and
 * timestamps are preserved so foreign keys keep pointing at the same
 * records; values are converted to the types the application stores
 * (ISO-8601 dates, booleans, integers, two-decimal strings).
 */
class DataImporter
{
    /**
     * Tables copied besides the domain tables.
     */
    private const EXTRA_TABLES = ['notifications'];

    public function __construct(private string $connection = 'mysql') {}

    /**
     * @param  array<int, string>  $only  Limit the import to these tables.
     * @return array<string, int> Rows written per table.
     */
    public function import(array $only = []): array
    {
        $counts = [];
        $registry = app(RepositoryRegistry::class);

        foreach ([...array_keys(RepositoryRegistry::TABLES), ...self::EXTRA_TABLES] as $table) {
            if ($only !== [] && ! in_array($table, $only, true)) {
                continue;
            }

            if ($this->hasTable($table)) {
                $counts[$table] = $this->importTable($table, $registry->for($table));
            }
        }

        if (($only === [] || in_array('settings', $only, true)) && $this->hasTable('settings')) {
            $counts['settings'] = $this->importSettings();
        }

        return $counts;
    }

    private function importTable(string $table, Repository $repository): int
    {
        $rows = $this->reader()->table($table)->orderBy('id')->get();
        $maxId = 0;

        foreach ($rows as $row) {
            $record = $this->normalize($table, (array) $row);
            $repository->put($record);

            if (is_int($record['id'])) {
                $maxId = max($maxId, $record['id']);
            }
        }

        if ($maxId > 0) {
            $repository->setNextId($maxId);
        }

        return $rows->count();
    }

    private function importSettings(): int
    {
        $rows = $this->reader()->table('settings')->get();
        $values = [];

        foreach ($rows as $row) {
            $values[$row->key] = $row->value;
        }

        if ($values !== []) {
            app(SettingRepository::class)->setMany($values);
        }

        return $rows->count();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(string $table, array $row): array
    {
        $schema = app(TableSchema::class);

        foreach ($row as $column => $value) {
            $type = $schema->type($table, $column)
                ?? (($column === 'id' || str_ends_with($column, '_id')) ? 'int' : null);

            $row[$column] = Values::store($value, $type);
        }

        return array_filter($row, fn ($value) => $value !== null);
    }

    private function hasTable(string $table): bool
    {
        return $this->reader()->getSchemaBuilder()->hasTable($table);
    }

    private function reader(): Connection
    {
        return DB::connection($this->connection);
    }
}
