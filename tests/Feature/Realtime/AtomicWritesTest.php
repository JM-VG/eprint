<?php

use App\Enums\JobStatus;
use App\Firebase\BatchingDatabaseClient;
use App\Firebase\DatabaseClient;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\User;
use App\Realtime\Atomic;
use App\Realtime\Locks;

it('commits every write of an atomic block together', function () {
    $inner = app(BatchingDatabaseClient::class)->inner();

    Atomic::run(function () use ($inner) {
        Location::create(['name' => 'Main', 'slug' => 'main']);
        Location::create(['name' => 'Annex', 'slug' => 'annex']);

        expect(Location::count())->toBe(2)
            ->and($inner->get('locations'))->toBeNull();
    });

    expect($inner->get('locations'))->toHaveCount(2);
});

it('writes nothing when an atomic block fails', function () {
    expect(fn () => Atomic::run(function () {
        Location::create(['name' => 'Main', 'slug' => 'main']);

        throw new RuntimeException('failed half way');
    }))->toThrow(RuntimeException::class, 'failed half way');

    expect(Location::count())->toBe(0);
});

it('holds named locks for the duration and releases them', function () {
    Atomic::run(function () {
        expect(app(DatabaseClient::class)->get('locks/print_jobs/5'))->toHaveKeys(['owner', 'expires_at']);
    }, ['print_jobs/5']);

    expect(app(DatabaseClient::class)->get('locks/print_jobs/5'))->toBeNull();
});

it('re-enters a lock already held by the same process', function () {
    $locks = app(Locks::class);

    $result = $locks->hold(['payouts/1'], fn () => $locks->hold(['payouts/1'], fn () => 'nested'));

    expect($result)->toBe('nested')
        ->and(app(DatabaseClient::class)->get('locks/payouts/1'))->toBeNull();
});

it('waits for a lock held elsewhere and gives up with a friendly error', function () {
    app(DatabaseClient::class)->set('locks/payouts/1', ['owner' => 'another-request', 'expires_at' => now()->addMinute()->getTimestamp()]);

    $locks = new Locks(app(DatabaseClient::class), waitMilliseconds: 200);

    expect(fn () => $locks->hold(['payouts/1'], fn () => 'never'))
        ->toThrow(RuntimeException::class, 'Another update is in progress');
});

it('takes over an expired lock', function () {
    app(DatabaseClient::class)->set('locks/payouts/1', ['owner' => 'crashed-request', 'expires_at' => now()->subMinute()->getTimestamp()]);

    expect(app(Locks::class)->hold(['payouts/1'], fn () => 'done'))->toBe('done');
});

it('reconciles counters, dangling references and commission amounts', function () {
    $staff = User::factory()->staff()->create();
    $sale = Sale::factory()->create(['user_id' => $staff->id, 'amount' => '100.00', 'commission_rate' => '10.00', 'commission_amount' => '10.00']);
    $sale->forceFill(['commission_amount' => '99.00'])->saveQuietly();
    app(DatabaseClient::class)->update("sales/{$sale->id}", ['payout_id' => 999]);
    app(DatabaseClient::class)->set('counters/sales', 0);

    $this->artisan('firebase:reconcile')->assertFailed();

    $this->artisan('firebase:reconcile', ['--fix' => true])->assertSuccessful();

    expect($sale->fresh()->commission_amount)->toBe('10.00')
        ->and($sale->fresh()->payout_id)->toBeNull()
        ->and(app(DatabaseClient::class)->get('counters/sales'))->toBeGreaterThanOrEqual($sale->id);

    $this->artisan('firebase:reconcile')->expectsOutput('No problems found.')->assertSuccessful();
});

it('reports a claimed job without a sale', function () {
    PrintJob::factory()->create(['status' => JobStatus::Claimed]);

    $this->artisan('firebase:reconcile', ['--fix' => true])
        ->expectsOutputToContain('is claimed but has 0 sales')
        ->assertFailed();
});
