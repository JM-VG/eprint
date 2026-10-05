<?php

use App\Firebase\ArrayDatabaseClient;

it('reads and writes nested values', function () {
    $database = new ArrayDatabaseClient;

    $database->set('locations/1', ['name' => 'Main IT Printing Room']);

    expect($database->get('locations/1'))->toBe(['name' => 'Main IT Printing Room'])
        ->and($database->get('locations'))->toBe([1 => ['name' => 'Main IT Printing Room']]);
});

it('returns null for a missing path', function () {
    $database = new ArrayDatabaseClient;

    expect($database->get('does/not/exist'))->toBeNull()
        ->and($database->exists('does/not/exist'))->toBeFalse();
});

it('merges updates without disturbing siblings', function () {
    $database = new ArrayDatabaseClient;
    $database->set('users/1', ['name' => 'Ana', 'role' => 'student']);

    $database->update('users/1', ['role' => 'staff']);

    expect($database->get('users/1'))->toBe(['name' => 'Ana', 'role' => 'staff']);
});

it('supports update keys that are relative paths', function () {
    $database = new ArrayDatabaseClient;
    $database->set('jobs/1', ['meta' => ['a' => 1, 'b' => 2]]);

    $database->update('jobs/1', ['meta/a' => 9]);

    expect($database->get('jobs/1/meta'))->toBe(['a' => 9, 'b' => 2]);
});

it('pushes values under generated keys', function () {
    $database = new ArrayDatabaseClient;

    $first = $database->push('logs', ['message' => 'one']);
    $second = $database->push('logs', ['message' => 'two']);

    expect($first)->not->toBe($second)
        ->and($database->get("logs/{$first}"))->toBe(['message' => 'one'])
        ->and($database->get("logs/{$second}"))->toBe(['message' => 'two']);
});

it('removes values', function () {
    $database = new ArrayDatabaseClient;
    $database->set('settings/key', 'value');

    $database->remove('settings/key');

    expect($database->exists('settings/key'))->toBeFalse()
        ->and($database->get('settings'))->toBe([]);
});

it('increments integers atomically', function () {
    $database = new ArrayDatabaseClient;

    expect($database->increment('counters/users'))->toBe(1)
        ->and($database->increment('counters/users'))->toBe(2)
        ->and($database->increment('counters/users', 5))->toBe(7)
        ->and($database->get('counters/users'))->toBe(7);
});

it('runs a transaction with the current value', function () {
    $database = new ArrayDatabaseClient;
    $database->set('counters/jobs', 10);

    $result = $database->transaction('counters/jobs', function ($current) {
        return $current + 3;
    });

    expect($result)->toBe(13)->and($database->get('counters/jobs'))->toBe(13);
});
