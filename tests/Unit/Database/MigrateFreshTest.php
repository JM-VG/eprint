<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('runs migrate:fresh without errors', function () {
    expect(Artisan::call('migrate:fresh'))->toBe(0);
    expect(Schema::hasTable('station_sessions'))->toBeTrue();

    foreach (['services', 'print_jobs', 'gcash_payments', 'sales', 'walkin_items', 'commission_payouts', 'job_logs', 'settings', 'announcements', 'notifications'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('rolls every migration back cleanly', function () {
    Artisan::call('migrate:fresh');

    expect(Artisan::call('migrate:reset'))->toBe(0);
    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('locations'))->toBeFalse()
        ->and(Schema::hasTable('station_sessions'))->toBeFalse()
        ->and(Schema::hasTable('print_jobs'))->toBeFalse()
        ->and(Schema::hasTable('sales'))->toBeFalse();
});

it('rolls back cleanly after seeding users without email', function () {
    config(['eprint.admin.password' => 'secret-pass-123']);
    Artisan::call('migrate:fresh', ['--seed' => true]);

    expect(DB::table('users')->whereNull('email')->exists())->toBeTrue();
    expect(Artisan::call('migrate:reset'))->toBe(0);
    expect(Schema::hasTable('users'))->toBeFalse();
});
