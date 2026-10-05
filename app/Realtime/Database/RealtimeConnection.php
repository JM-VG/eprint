<?php

namespace App\Realtime\Database;

use App\Firebase\BatchingDatabaseClient;
use App\Realtime\Database\Stores\RecordStore;
use App\Realtime\Database\Stores\SettingStore;
use App\Realtime\Database\Stores\TableStore;
use App\Repositories\RepositoryRegistry;
use Closure;
use Illuminate\Database\Connection;
use Throwable;

/**
 * A database connection whose queries run against the Firebase Realtime
 * Database instead of SQL.
 *
 * Eloquent models, relations, factories and pagination keep working: the
 * {@see QueryBuilder} evaluates where clauses, ordering, limits and
 * aggregates in PHP over the records each table's repository returns.
 * A "transaction" is a write batch committed as one atomic multi-path
 * update; there is no row locking.
 */
class RealtimeConnection extends Connection
{
    /**
     * @var array<string, TableStore>
     */
    private array $stores = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private BatchingDatabaseClient $client,
        private RepositoryRegistry $repositories,
        private TableSchema $tableSchema,
        array $config = [],
    ) {
        parent::__construct(null, $config['database'] ?? 'firebase', '', $config);
    }

    public function getDriverName()
    {
        return 'rtdb';
    }

    public function getDriverTitle()
    {
        return 'Firebase Realtime Database';
    }

    protected function getDefaultQueryGrammar()
    {
        return new QueryGrammar($this);
    }

    public function query()
    {
        return new QueryBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }

    public function store(string $table): TableStore
    {
        return $this->stores[$table] ??= $table === 'settings'
            ? new SettingStore($this->repositories->settings())
            : new RecordStore($this->repositories->for($table), array_keys($this->tableSchema->columns($table)));
    }

    public function tableSchema(): TableSchema
    {
        return $this->tableSchema;
    }

    public function client(): BatchingDatabaseClient
    {
        return $this->client;
    }

    /**
     * Run the callback as one write batch: every write it makes is committed
     * together at the end, or discarded when it throws.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function atomically(callable $callback): mixed
    {
        return $this->client->batch($callback);
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
        } catch (Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        $this->commit();

        return $result;
    }

    public function beginTransaction()
    {
        $this->client->begin();
        $this->transactions++;
        $this->transactionsManager?->begin($this->getName(), $this->transactions);
        $this->fireConnectionEvent('beganTransaction');
    }

    public function commit()
    {
        if ($this->transactions === 1) {
            $this->fireConnectionEvent('committing');
        }

        $this->client->commit();
        $level = $this->transactions;
        $this->transactions = max(0, $this->transactions - 1);
        $this->transactionsManager?->commit($this->getName(), $level, $this->transactions);
        $this->fireConnectionEvent('committed');
    }

    public function rollBack($toLevel = null)
    {
        if ($this->transactions === 0) {
            return;
        }

        $this->client->rollBack();
        $this->transactions = max(0, $this->transactions - 1);
        $this->transactionsManager?->rollback($this->getName(), $this->transactions);
        $this->fireConnectionEvent('rollingBack');
    }

    public function transactionLevel()
    {
        return $this->transactions;
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        throw UnsupportedQuery::because('raw SQL cannot run against the Realtime Database');
    }

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        throw UnsupportedQuery::because('raw SQL cannot run against the Realtime Database');
    }

    public function statement($query, $bindings = [])
    {
        throw UnsupportedQuery::because('raw SQL cannot run against the Realtime Database');
    }

    public function affectingStatement($query, $bindings = [])
    {
        throw UnsupportedQuery::because('raw SQL cannot run against the Realtime Database');
    }

    public function unprepared($query)
    {
        throw UnsupportedQuery::because('raw SQL cannot run against the Realtime Database');
    }

    public function getSchemaBuilder()
    {
        throw UnsupportedQuery::because('the Realtime Database has no schema; see '.TableSchema::class);
    }
}
