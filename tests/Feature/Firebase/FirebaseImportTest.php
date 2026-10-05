<?php

use App\Models\Location;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\LocationRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;

it('imports rows preserving ids and values', function () {
    $location = Location::factory()->create(['name' => 'Main IT Printing Room', 'slug' => 'main-it']);
    $service = Service::factory()->create(['name' => 'Short B&W']);
    $user = User::factory()->create(['student_id' => 'MMC2024-00123']);

    $this->artisan('firebase:import', ['--connection' => 'sqlite'])->assertSuccessful();

    expect(app(LocationRepository::class)->find($location->id)['name'])->toBe('Main IT Printing Room')
        ->and(app(LocationRepository::class)->find($location->id)['id'])->toBe($location->id)
        ->and(app(ServiceRepository::class)->find($service->id)['name'])->toBe('Short B&W')
        ->and(app(UserRepository::class)->find($user->id)['student_id'])->toBe('MMC2024-00123');
});

it('imports settings as a key/value map', function () {
    Setting::create(['key' => 'retention_days', 'value' => '14']);

    $this->artisan('firebase:import', ['--connection' => 'sqlite'])->assertSuccessful();

    expect(app(SettingRepository::class)->get('retention_days'))->toBe('14');
});

it('advances id counters so new records do not collide', function () {
    Location::factory()->count(4)->create();

    $this->artisan('firebase:import', ['--connection' => 'sqlite'])->assertSuccessful();

    expect(app(LocationRepository::class)->create(['name' => 'New', 'slug' => 'new'])['id'])->toBe(5);
});

it('can limit the import to specific tables', function () {
    Location::factory()->create();
    Service::factory()->create();

    $this->artisan('firebase:import', ['--connection' => 'sqlite', '--table' => ['locations']])->assertSuccessful();

    expect(app(LocationRepository::class)->count())->toBe(1)
        ->and(app(ServiceRepository::class)->count())->toBe(0);
});
