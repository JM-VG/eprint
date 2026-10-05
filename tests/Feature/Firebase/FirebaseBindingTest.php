<?php

use App\Firebase\ArrayDatabaseClient;
use App\Firebase\DatabaseClient;

it('binds the in-memory database client while testing', function () {
    expect(app(DatabaseClient::class))->toBeInstanceOf(ArrayDatabaseClient::class);
});

it('resolves the database client as a singleton', function () {
    expect(app(DatabaseClient::class))->toBe(app(DatabaseClient::class));
});
