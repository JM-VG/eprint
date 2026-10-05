<?php

use App\Repositories\LocationRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| The application itself no longer uses SQL; these tests build a small
| relational source database (in-memory SQLite) to import from.
*/

beforeEach(function () {
    $schema = Schema::connection('sqlite');

    $schema->create('locations', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    });

    $schema->create('services', function (Blueprint $table) {
        $table->id();
        $table->string('type');
        $table->string('name');
        $table->string('unit');
        $table->decimal('price', 10, 2)->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('student_id')->nullable();
        $table->string('name');
        $table->string('password');
        $table->string('role')->default('student');
        $table->string('status')->default('pending');
        $table->boolean('must_change_password')->default(false);
        $table->timestamps();
    });

    $schema->create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique();
        $table->text('value')->nullable();
        $table->timestamps();
    });
});

function sourceRow(string $table, array $values): int
{
    return DB::connection('sqlite')->table($table)->insertGetId($values + ['created_at' => '2026-09-30 08:00:00', 'updated_at' => '2026-09-30 08:00:00']);
}

function sourceLocation(array $values = []): int
{
    static $sequence = 0;
    $sequence++;

    return sourceRow('locations', $values + ['name' => "Room {$sequence}", 'slug' => "room-{$sequence}-".uniqid()]);
}

it('imports rows preserving ids and values', function () {
    $location = sourceLocation(['name' => 'Main IT Printing Room', 'slug' => 'main-it']);
    $service = sourceRow('services', ['type' => 'document', 'name' => 'Short B&W', 'unit' => 'page', 'price' => 2]);
    $user = sourceRow('users', ['student_id' => 'MMC2024-00123', 'name' => 'Ana', 'password' => 'x']);

    $this->artisan('firebase:import', ['--connection' => 'sqlite'])->assertSuccessful();

    expect(app(LocationRepository::class)->find($location)['name'])->toBe('Main IT Printing Room')
        ->and(app(LocationRepository::class)->find($location)['id'])->toBe($location)
        ->and(app(ServiceRepository::class)->find($service)['name'])->toBe('Short B&W')
        ->and(app(UserRepository::class)->find($user)['student_id'])->toBe('MMC2024-00123');
});

it('converts values to the stored types', function () {
    $service = sourceRow('services', ['type' => 'document', 'name' => 'Short B&W', 'unit' => 'page', 'price' => 2, 'is_active' => 1]);

    $this->artisan('firebase:import', ['--connection' => 'sqlite'])->assertSuccessful();

    expect(app(ServiceRepository::class)->find($service))->toMatchArray([
        'price' => '2.00',
        'is_active' => true,
        'created_at' => '2026-09-30T08:00:00+08:00',
    ]);
});

it('imports settings as a key/value map', function () {
    sourceRow('settings', ['key' => 'retention_days', 'value' => '14']);

    $this->artisan('firebase:import', ['--connection' => 'sqlite'])->assertSuccessful();

    expect(app(SettingRepository::class)->get('retention_days'))->toBe('14');
});

it('advances id counters so new records do not collide', function () {
    foreach (range(1, 4) as $i) {
        sourceLocation();
    }

    $this->artisan('firebase:import', ['--connection' => 'sqlite'])->assertSuccessful();

    expect(app(LocationRepository::class)->create(['name' => 'New', 'slug' => 'new'])['id'])->toBe(5);
});

it('can limit the import to specific tables', function () {
    sourceLocation();
    sourceRow('services', ['type' => 'document', 'name' => 'Short B&W', 'unit' => 'page', 'price' => 2]);

    $this->artisan('firebase:import', ['--connection' => 'sqlite', '--table' => ['locations']])->assertSuccessful();

    expect(app(LocationRepository::class)->count())->toBe(1)
        ->and(app(ServiceRepository::class)->count())->toBe(0);
});
