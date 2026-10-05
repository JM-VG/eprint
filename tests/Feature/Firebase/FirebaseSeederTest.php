<?php

use App\Repositories\LocationRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use Database\Seeders\Firebase\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config([
        'eprint.admin.username' => 'admin',
        'eprint.admin.password' => 'secret',
    ]);
});

it('seeds base data into the realtime database', function () {
    $this->seed(DatabaseSeeder::class);

    expect(app(LocationRepository::class)->count())->toBe(2)
        ->and(app(ServiceRepository::class)->count())->toBe(9)
        ->and(app(UserRepository::class)->count())->toBe(1)
        ->and(app(SettingRepository::class)->all())->toHaveKeys([
            'commission_rate', 'gcash_name', 'gcash_number',
            'gcash_qr_path', 'retention_days', 'student_id_pattern',
        ]);
});

it('creates the admin from configuration with a hashed password', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = app(UserRepository::class)->findByUsername('admin');

    expect($admin['role'])->toBe('admin')
        ->and($admin['status'])->toBe('approved')
        ->and($admin['password'])->not->toBe('secret')
        ->and(Hash::check('secret', $admin['password']))->toBeTrue();
});

it('is safe to seed twice without creating duplicates', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(app(LocationRepository::class)->count())->toBe(2)
        ->and(app(ServiceRepository::class)->count())->toBe(9)
        ->and(app(UserRepository::class)->count())->toBe(1);
});

it('exposes a firebase:seed command', function () {
    $this->artisan('firebase:seed')->assertSuccessful();

    expect(app(LocationRepository::class)->count())->toBe(2)
        ->and(app(SettingRepository::class)->get('retention_days'))->toBe('7');
});
