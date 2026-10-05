<?php

use App\Firebase\ArrayDatabaseClient;
use App\Repositories\Repository;

class HelperWidgetRepository extends Repository
{
    public function table(): string
    {
        return 'widgets';
    }
}

function helperWidgets(): HelperWidgetRepository
{
    return new HelperWidgetRepository(new ArrayDatabaseClient);
}

it('finds the first record matching a column', function () {
    $repository = helperWidgets();
    $repository->create(['name' => 'A', 'user_id' => 5]);
    $repository->create(['name' => 'B', 'user_id' => 7]);

    expect($repository->firstWhere('user_id', 7)['name'])->toBe('B')
        ->and($repository->firstWhere('user_id', 99))->toBeNull();
});

it('treats numeric strings and integers as equal', function () {
    $repository = helperWidgets();
    $repository->create(['name' => 'Numeric', 'user_id' => 5]);

    expect($repository->firstWhere('user_id', '5')['name'])->toBe('Numeric')
        ->and($repository->whereColumn('user_id', '5'))->toHaveCount(1);
});

it('filters with whereIn and whereNull', function () {
    $repository = helperWidgets();
    $repository->create(['name' => 'A', 'status' => 'pending', 'payout_id' => null]);
    $repository->create(['name' => 'B', 'status' => 'ready', 'payout_id' => 3]);
    $repository->create(['name' => 'C', 'status' => 'claimed', 'payout_id' => 4]);

    expect($repository->whereIn('status', ['pending', 'ready']))->toHaveCount(2)
        ->and($repository->whereNull('payout_id'))->toHaveCount(1)
        ->and($repository->whereNull('payout_id')[0]['name'])->toBe('A');
});

it('counts records and sums a column', function () {
    $repository = helperWidgets();
    $repository->create(['amount' => '10.50']);
    $repository->create(['amount' => '4.50']);

    expect($repository->count())->toBe(2)
        ->and($repository->sum('amount'))->toBe(15.0);
});

it('plucks a column with and without a key', function () {
    $repository = helperWidgets();
    $a = $repository->create(['name' => 'A', 'amount' => '1.00']);
    $repository->create(['name' => 'B', 'amount' => '2.00']);

    expect($repository->pluck('name'))->toBe(['A', 'B'])
        ->and($repository->pluck('name', 'id')[$a['id']])->toBe('A');
});

it('truncates a table and resets its counter', function () {
    $repository = helperWidgets();
    $repository->create(['name' => 'A']);
    $repository->create(['name' => 'B']);

    $repository->truncate();

    expect($repository->count())->toBe(0)
        ->and($repository->create(['name' => 'C'])['id'])->toBe(1);
});
