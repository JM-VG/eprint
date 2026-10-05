<?php

namespace App\Providers;

use App\Auth\RealtimeUserProvider;
use App\Firebase\ArrayDatabaseClient;
use App\Firebase\BatchingDatabaseClient;
use App\Firebase\DatabaseClient;
use App\Firebase\FirebaseDatabaseClient;
use App\Firebase\ServiceAccountTokenProvider;
use App\Realtime\Database\RealtimeConnection;
use App\Realtime\Database\TableSchema;
use App\Repositories\RepositoryRegistry;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseClient::class, function ($app) {
            $config = $app['config']->get('firebase');

            $driver = $config['driver'] ?? 'auto';

            if ($driver === 'auto') {
                $driver = $app->runningUnitTests() ? 'array' : 'firebase';
            }

            if ($driver === 'array') {
                return new ArrayDatabaseClient;
            }

            if ($driver !== 'firebase') {
                throw new RuntimeException("Unsupported Firebase driver [{$driver}]. Use auto, array or firebase.");
            }

            return new FirebaseDatabaseClient(
                databaseUrl: $config['database_url'],
                tokens: new ServiceAccountTokenProvider(
                    credentials: $config['credentials'],
                    useCache: ($config['cache_tokens'] ?? true) && ! $app->runningUnitTests(),
                ),
                maxTransactionRetries: (int) ($config['transaction_retries'] ?? 5),
            );
        });

        // Repositories (and so Eloquent) write through one batching client, so
        // a write batch sees its own changes and commits them atomically.
        $this->app->singleton(BatchingDatabaseClient::class, fn ($app) => new BatchingDatabaseClient($app->make(DatabaseClient::class)));

        $this->app->when([...array_values(RepositoryRegistry::TABLES), SettingRepository::class, RepositoryRegistry::class])
            ->needs(DatabaseClient::class)
            ->give(fn ($app) => $app->make(BatchingDatabaseClient::class));

        $this->app->singleton(TableSchema::class);
        $this->app->singleton(RepositoryRegistry::class);

        $this->app->resolving('db', function (DatabaseManager $db, $app) {
            $db->extend('rtdb', fn (array $config, string $name) => new RealtimeConnection(
                $app->make(BatchingDatabaseClient::class),
                $app->make(RepositoryRegistry::class),
                $app->make(TableSchema::class),
                ['name' => $name] + $config,
            ));
        });
    }

    public function boot(): void
    {
        Auth::provider('rtdb', fn ($app) => new RealtimeUserProvider(
            $app->make(UserRepository::class),
        ));
    }
}
