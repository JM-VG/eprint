<?php

namespace App\Firebase;

use App\Repositories\AnnouncementRepository;
use App\Repositories\CommissionPayoutRepository;
use App\Repositories\GcashPaymentRepository;
use App\Repositories\JobLogRepository;
use App\Repositories\LocationRepository;
use App\Repositories\PrintJobRepository;
use App\Repositories\Repository;
use App\Repositories\SaleRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SettingRepository;
use App\Repositories\StationSessionRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalkinItemRepository;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill of the Firebase Realtime Database from a relational
 * source database. Ids and timestamps are preserved so foreign keys keep
 * pointing at the same records after the application cutover.
 */
class DataImporter
{
    /**
     * Relational table => repository that owns the matching RTDB node.
     *
     * @var array<string, class-string<Repository>>
     */
    private const TABLES = [
        'users' => UserRepository::class,
        'locations' => LocationRepository::class,
        'services' => ServiceRepository::class,
        'station_sessions' => StationSessionRepository::class,
        'print_jobs' => PrintJobRepository::class,
        'gcash_payments' => GcashPaymentRepository::class,
        'sales' => SaleRepository::class,
        'walkin_items' => WalkinItemRepository::class,
        'commission_payouts' => CommissionPayoutRepository::class,
        'job_logs' => JobLogRepository::class,
        'announcements' => AnnouncementRepository::class,
    ];

    public function __construct(private string $connection = 'mysql') {}

    /**
     * @param  array<int, string>  $only  Limit the import to these tables.
     * @return array<string, int> Rows written per table.
     */
    public function import(array $only = []): array
    {
        $counts = [];

        foreach (self::TABLES as $table => $repositoryClass) {
            if ($only !== [] && ! in_array($table, $only, true)) {
                continue;
            }

            $counts[$table] = $this->importTable($table, app($repositoryClass));
        }

        if ($only === [] || in_array('settings', $only, true)) {
            $counts['settings'] = $this->importSettings();
        }

        return $counts;
    }

    /**
     * @param  class-string<Repository>|Repository  $repository
     */
    private function importTable(string $table, Repository $repository): int
    {
        $rows = $this->reader()->table($table)->orderBy('id')->get();
        $maxId = 0;

        foreach ($rows as $row) {
            $record = $this->normalize((array) $row);
            $repository->put($record);
            $maxId = max($maxId, (int) $record['id']);
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
    private function normalize(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $row[$key] = Carbon::instance($value)->toIso8601String();

                continue;
            }

            if (($key === 'id' || str_ends_with($key, '_id')) && is_numeric($value) && preg_match('/^-?\d+$/', (string) $value)) {
                $row[$key] = (int) $value;
            }
        }

        return $row;
    }

    private function reader(): Connection
    {
        return DB::connection($this->connection);
    }
}
