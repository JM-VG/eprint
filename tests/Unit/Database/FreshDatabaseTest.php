<?php

use App\Firebase\DatabaseClient;
use App\Repositories\RepositoryRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

/*
| The Realtime Database replacement for migrate:fresh / migrate:reset is
| firebase:seed --fresh: clear every table and id counter, then seed.
*/

it('seeds a fresh database without errors', function () {
    config(['eprint.admin.password' => 'secret-pass-123']);

    expect(Artisan::call('firebase:seed', ['--fresh' => true]))->toBe(0);

    foreach (['users', 'locations', 'services', 'settings'] as $table) {
        expect(DB::table($table)->count())->toBeGreaterThan(0);
    }
});

it('clears every table and id counter', function () {
    config(['eprint.admin.password' => 'secret-pass-123']);
    Artisan::call('firebase:seed');
    DB::table('station_sessions')->insert(['user_id' => 1, 'location_id' => 1, 'opened_at' => now(), 'last_seen_at' => now()]);

    expect(Artisan::call('firebase:seed', ['--fresh' => true]))->toBe(0);

    expect(DB::table('station_sessions')->count())->toBe(0)
        ->and(app(DatabaseClient::class)->get('counters/station_sessions'))->toBeNull()
        ->and(DB::table('users')->count())->toBe(1)
        ->and(DB::table('users')->value('id'))->toBe(1);
});

it('clears users seeded without an email', function () {
    config(['eprint.admin.password' => 'secret-pass-123']);
    Artisan::call('firebase:seed');

    expect(DB::table('users')->whereNull('email')->exists())->toBeTrue();

    app(RepositoryRegistry::class)->for('users')->truncate();

    expect(DB::table('users')->exists())->toBeFalse();
});
