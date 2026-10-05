<?php

namespace App\Realtime;

use App\Firebase\BatchingDatabaseClient;
use App\Firebase\DatabaseClient;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Short-lived mutexes stored at "/locks/{name}" in the Realtime Database.
 *
 * They replace SQL row locks (SELECT ... FOR UPDATE) for the few writes that
 * must not run twice at once, e.g. a double-submitted claim or payout.
 * Acquiring uses a server-side transaction (compare-and-set); a lock expires
 * on its own if its holder dies. Locks are re-entrant within one process.
 */
class Locks
{
    /**
     * @var array<string, array{token: string, depth: int}>
     */
    private array $held = [];

    public function __construct(
        private DatabaseClient $database,
        private int $ttlSeconds = 15,
        private int $waitMilliseconds = 5000,
    ) {}

    /**
     * Run the callback while holding every named lock.
     *
     * @template T
     *
     * @param  list<string>  $names
     * @param  callable(): T  $callback
     * @return T
     */
    public function hold(array $names, callable $callback): mixed
    {
        $names = array_values(array_unique($names));
        sort($names);
        $acquired = [];

        try {
            foreach ($names as $name) {
                $this->acquire($name);
                $acquired[] = $name;
            }

            return $callback();
        } finally {
            foreach (array_reverse($acquired) as $name) {
                $this->release($name);
            }
        }
    }

    public function acquire(string $name): void
    {
        if (isset($this->held[$name])) {
            $this->held[$name]['depth']++;

            return;
        }

        $token = (string) Str::uuid();
        $deadline = microtime(true) + $this->waitMilliseconds / 1000;

        do {
            $now = now()->getTimestamp();

            $lock = $this->database->transaction($this->path($name), function ($current) use ($token, $now) {
                $free = ! is_array($current) || (int) ($current['expires_at'] ?? 0) <= $now;

                return $free ? ['owner' => $token, 'expires_at' => $now + $this->ttlSeconds] : $current;
            });

            if (($lock['owner'] ?? null) === $token) {
                $this->held[$name] = ['token' => $token, 'depth' => 1];

                // Whatever was read before the lock may already be out of date.
                if ($this->database instanceof BatchingDatabaseClient) {
                    $this->database->flushReadCache();
                }

                return;
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Another update is in progress. Please try again.');
    }

    public function release(string $name): void
    {
        if (! isset($this->held[$name])) {
            return;
        }

        if (--$this->held[$name]['depth'] > 0) {
            return;
        }

        $token = $this->held[$name]['token'];
        unset($this->held[$name]);

        $this->database->transaction(
            $this->path($name),
            fn ($current) => is_array($current) && ($current['owner'] ?? null) === $token ? null : $current,
        );
    }

    private function path(string $name): string
    {
        return 'locks/'.preg_replace('/[.#$\[\]]/', '_', trim($name, '/'));
    }
}
