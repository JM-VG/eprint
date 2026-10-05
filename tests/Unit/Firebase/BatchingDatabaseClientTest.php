<?php

use App\Firebase\ArrayDatabaseClient;
use App\Firebase\BatchingDatabaseClient;

/**
 * Records every multi-path update so a test can see what was sent at once.
 */
class RecordingDatabaseClient extends ArrayDatabaseClient
{
    /** @var list<array<string, mixed>> */
    public array $updates = [];

    public function update(string $path, array $values): void
    {
        $this->updates[] = $values;
        parent::update($path, $values);
    }
}

it('writes straight through outside a batch', function () {
    $inner = new ArrayDatabaseClient;
    $client = new BatchingDatabaseClient($inner);

    $client->set('items/1', ['name' => 'A']);
    $client->update('items/1', ['size' => 2]);
    $client->remove('items/2');

    expect($inner->get('items/1'))->toBe(['name' => 'A', 'size' => 2]);
});

it('overlays buffered writes on reads inside a batch', function () {
    $inner = new ArrayDatabaseClient(['items' => [1 => ['name' => 'A', 'size' => 1]]]);
    $client = new BatchingDatabaseClient($inner);

    $client->batch(function () use ($client, $inner) {
        $client->update('items/1', ['size' => 5]);
        $client->set('items/2', ['name' => 'B']);
        $client->remove('items/1/name');

        expect($client->get('items'))->toBe([1 => ['size' => 5], 2 => ['name' => 'B']])
            ->and($client->get('items/2/name'))->toBe('B')
            ->and($inner->get('items/2'))->toBeNull();
    });

    expect($inner->get('items'))->toBe([1 => ['size' => 5], 2 => ['name' => 'B']]);
});

it('commits overlapping writes as one non-overlapping multi-path update', function () {
    $inner = new RecordingDatabaseClient;
    $client = new BatchingDatabaseClient($inner);

    $client->batch(function () use ($client) {
        $client->set('items/1', ['name' => 'A']);
        $client->update('items/1', ['size' => 3]);
        $client->update('items/2', ['name' => 'B']);
        $client->set('items/2', ['name' => 'C']);
    });

    expect($inner->updates)->toBe([['items/1' => ['name' => 'A', 'size' => 3], 'items/2' => ['name' => 'C']]]);
});

it('discards a rolled back batch and keeps savepoints for nested batches', function () {
    $inner = new ArrayDatabaseClient;
    $client = new BatchingDatabaseClient($inner);

    $client->batch(function () use ($client) {
        $client->set('items/1', ['name' => 'kept']);

        try {
            $client->batch(function () use ($client) {
                $client->set('items/2', ['name' => 'dropped']);

                throw new RuntimeException('inner');
            });
        } catch (RuntimeException) {
            //
        }
    });

    expect($inner->get('items'))->toBe([1 => ['name' => 'kept']]);

    expect(fn () => $client->batch(function () use ($client) {
        $client->set('items/3', ['name' => 'never']);

        throw new RuntimeException('outer');
    }))->toThrow(RuntimeException::class);

    expect($inner->get('items/3'))->toBeNull()
        ->and($client->inBatch())->toBeFalse();
});

it('does not buffer counters', function () {
    $inner = new ArrayDatabaseClient;
    $client = new BatchingDatabaseClient($inner);

    $client->batch(function () use ($client, $inner) {
        expect($client->increment('counters/items'))->toBe(1)
            ->and($inner->get('counters/items'))->toBe(1);
    });
});
