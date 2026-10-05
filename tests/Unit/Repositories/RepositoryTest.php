<?php

use App\Firebase\ArrayDatabaseClient;
use App\Repositories\Repository;

class TestWidgetRepository extends Repository
{
    public function table(): string
    {
        return 'widgets';
    }
}

function widgetRepository(): TestWidgetRepository
{
    return new TestWidgetRepository(new ArrayDatabaseClient);
}

it('assigns sequential integer ids from an atomic counter', function () {
    $repository = widgetRepository();

    $first = $repository->create(['name' => 'First']);
    $second = $repository->create(['name' => 'Second']);

    expect($first['id'])->toBe(1)
        ->and($second['id'])->toBe(2)
        ->and($repository->find(1)['name'])->toBe('First');
});

it('stamps created and updated timestamps', function () {
    $repository = widgetRepository();

    $record = $repository->create(['name' => 'Stamp']);

    expect($record)->toHaveKeys(['created_at', 'updated_at'])
        ->and($record['created_at'])->toBe($record['updated_at']);
});

it('finds and reports existence', function () {
    $repository = widgetRepository();
    $record = $repository->create(['name' => 'Findable']);

    expect($repository->find($record['id']))->toBe($record)
        ->and($repository->find(999))->toBeNull()
        ->and($repository->exists($record['id']))->toBeTrue()
        ->and($repository->exists(999))->toBeFalse();
});

it('updates an existing record and touches updated_at', function () {
    $repository = widgetRepository();
    $record = $repository->create(['name' => 'Old', 'active' => false]);

    $updated = $repository->update($record['id'], ['name' => 'New']);

    expect($updated['name'])->toBe('New')
        ->and($updated['active'])->toBeFalse()
        ->and($updated['id'])->toBe($record['id']);
});

it('returns null when updating a missing record', function () {
    expect(widgetRepository()->update(123, ['name' => 'x']))->toBeNull();
});

it('deletes records', function () {
    $repository = widgetRepository();
    $record = $repository->create(['name' => 'Temporary']);

    expect($repository->delete($record['id']))->toBeTrue()
        ->and($repository->delete($record['id']))->toBeFalse()
        ->and($repository->find($record['id']))->toBeNull();
});

it('returns all records keyed by id', function () {
    $repository = widgetRepository();
    $repository->create(['name' => 'A']);
    $repository->create(['name' => 'B']);

    expect($repository->all())->toHaveCount(2)
        ->and($repository->all()[1]['name'])->toBe('A');
});

it('filters records in memory with where', function () {
    $repository = widgetRepository();
    $repository->create(['name' => 'A', 'active' => true]);
    $repository->create(['name' => 'B', 'active' => false]);

    $active = $repository->where(fn (array $record) => $record['active'] === true);

    expect($active)->toHaveCount(1)
        ->and($active[0]['name'])->toBe('A');
});
