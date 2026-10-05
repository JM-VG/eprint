<?php

namespace App\Realtime;

use App\Firebase\BatchingDatabaseClient;
use App\Services\AvailabilityService;
use App\Services\QueueService;
use Illuminate\Contracts\Container\Container;

/**
 * Small, non-personal summaries the browser may read straight from the
 * Realtime Database (database.rules.json allows reading /public only):
 *
 * - /public/availability: each location's open flag and staff count
 * - /public/queue: pending jobs per location id
 *
 * They are republished after any committed write to the tables they are
 * computed from, so the availability banner and queue badge update live.
 */
class PublicProjections
{
    public const AVAILABILITY = 'public/availability';

    public const QUEUE = 'public/queue';

    /**
     * Tables whose changes affect each projection.
     */
    private const SOURCES = [
        self::AVAILABILITY => ['station_sessions', 'locations'],
        self::QUEUE => ['print_jobs', 'locations'],
    ];

    private bool $publishing = false;

    public function __construct(private BatchingDatabaseClient $database, private Container $container) {}

    /**
     * Republish the projections affected by the written paths.
     *
     * @param  list<string>  $paths
     */
    public function handle(array $paths): void
    {
        if ($this->publishing) {
            return;
        }

        $tables = array_unique(array_map(fn (string $path) => explode('/', $path, 2)[0], $paths));
        $root = in_array('', $paths, true);

        $this->publishing = true;

        try {
            foreach (self::SOURCES as $projection => $sources) {
                if ($root || array_intersect($sources, $tables) !== []) {
                    $projection === self::AVAILABILITY ? $this->publishAvailability() : $this->publishQueue();
                }
            }
        } finally {
            $this->publishing = false;
        }
    }

    /**
     * The streaming URL the browser subscribes to, or null when live updates are off.
     */
    public static function url(string $projection): ?string
    {
        $databaseUrl = config('firebase.web.database_url');

        return config('firebase.realtime_ui') && filled($databaseUrl)
            ? rtrim((string) $databaseUrl, '/').'/'.$projection.'.json'
            : null;
    }

    public function publishAvailability(): void
    {
        $this->database->set(self::AVAILABILITY, [
            'locations' => $this->container->make(AvailabilityService::class)->locations(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function publishQueue(): void
    {
        $counts = [];

        foreach ($this->container->make(QueueService::class)->pendingCounts() as $locationId => $count) {
            $counts[(string) $locationId] = $count;
        }

        $this->database->set(self::QUEUE, [
            'pending' => $counts,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function publishAll(): void
    {
        $this->publishing = true;

        try {
            $this->publishAvailability();
            $this->publishQueue();
        } finally {
            $this->publishing = false;
        }
    }
}
