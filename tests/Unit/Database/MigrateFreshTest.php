<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('runs migrate:fresh without errors', function () {
    expect(Artisan::call('migrate:fresh'))->toBe(0);
    expect(Schema::hasTable('station_sessions'))->toBeTrue();
});

it('rolls every migration back cleanly', function () {
    Artisan::call('migrate:fresh');

    expect(Artisan::call('migrate:reset'))->toBe(0);
    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('locations'))->toBeFalse()
        ->and(Schema::hasTable('station_sessions'))->toBeFalse();
});
