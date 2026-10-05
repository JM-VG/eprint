<?php

namespace App\Providers;

use App\Firebase\ArrayDatabaseClient;
use App\Firebase\DatabaseClient;
use App\Firebase\FirebaseDatabaseClient;
use App\Firebase\ServiceAccountTokenProvider;
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
    }
}
