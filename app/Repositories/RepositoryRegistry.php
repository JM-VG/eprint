<?php

namespace App\Repositories;

use App\Firebase\DatabaseClient;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the repository that owns a table's Realtime Database node.
 */
class RepositoryRegistry
{
    /**
     * @var array<string, class-string<Repository>>
     */
    public const TABLES = [
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

    /**
     * @var array<string, Repository>
     */
    private array $resolved = [];

    public function __construct(private Container $container, private DatabaseClient $database) {}

    public function for(string $table): Repository
    {
        return $this->resolved[$table] ??= isset(self::TABLES[$table])
            ? $this->container->make(self::TABLES[$table])
            : new TableRepository($this->database, $table);
    }

    public function settings(): SettingRepository
    {
        return $this->container->make(SettingRepository::class);
    }
}
