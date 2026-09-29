<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config([
        'eprint.admin.username' => 'rootadmin',
        'eprint.admin.password' => 'secret-pass-123',
    ]);
});

function tableCounts(): array
{
    return [
        'users' => User::count(),
        'locations' => Location::count(),
        'services' => Service::count(),
        'settings' => Setting::count(),
        'print_jobs' => PrintJob::count(),
    ];
}

it('seeds the two printing room locations', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Location::orderBy('id')->pluck('name')->all())
        ->toBe(['Main IT Printing Room', 'CCS Office']);
});

it('seeds the admin from the configured username and password', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('username', 'rootadmin')->sole();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->status)->toBe(UserStatus::Approved)
        ->and($admin->student_id)->toBeNull()
        ->and(Hash::check('secret-pass-123', $admin->password))->toBeTrue();
});

it('does not overwrite an existing admin password when re-seeded', function () {
    $this->seed(DatabaseSeeder::class);
    User::where('username', 'rootadmin')->update(['password' => Hash::make('changed-later')]);

    config(['eprint.admin.password' => 'another-pass']);
    $this->seed(DatabaseSeeder::class);

    expect(Hash::check('changed-later', User::where('username', 'rootadmin')->sole()->password))->toBeTrue();
});

it('skips the admin when no password is configured', function () {
    config(['eprint.admin.password' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('role', Role::Admin)->count())->toBe(0);
});

it('seeds sample document and photo services priced at zero', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Service::where('type', 'document')->count())->toBeGreaterThan(0)
        ->and(Service::where('type', 'photo')->pluck('name')->all())->toContain('1x1', '2x2', 'Passport')
        ->and(Service::where('price', '!=', 0)->count())->toBe(0);
});

it('seeds the default settings', function () {
    $this->seed(DatabaseSeeder::class);

    $settings = Setting::pluck('value', 'key');

    expect($settings->keys()->all())->toContain(
        'commission_rate', 'gcash_name', 'gcash_number', 'gcash_qr_path', 'retention_days', 'student_id_pattern'
    )
        ->and($settings['retention_days'])->toBe('7')
        ->and($settings['student_id_pattern'])->toBe('^[A-Z]{3}\d{4}-\d{5}$');
});

it('keeps admin-edited settings when re-seeded', function () {
    $this->seed(DatabaseSeeder::class);
    Setting::where('key', 'retention_days')->update(['value' => '30']);

    $this->seed(DatabaseSeeder::class);

    expect(Setting::where('key', 'retention_days')->value('value'))->toBe('30');
});

it('can run db:seed twice without duplicates', function () {
    $this->artisan('db:seed')->assertSuccessful();
    $first = tableCounts();

    $this->artisan('db:seed')->assertSuccessful();

    expect(tableCounts())->toBe($first);
});

it('does not seed demo data outside the local environment', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('role', Role::Student)->count())->toBe(0)
        ->and(PrintJob::count())->toBe(0);

    $this->seed(DemoSeeder::class);

    expect(User::where('role', Role::Student)->count())->toBe(0)
        ->and(PrintJob::count())->toBe(0);
});

it('seeds demo data in the local environment without duplicates', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(DatabaseSeeder::class);
    $first = tableCounts();

    expect(User::where('role', Role::Student)->count())->toBeGreaterThan(0)
        ->and(User::where('role', Role::Staff)->count())->toBeGreaterThan(0)
        ->and($first['print_jobs'])->toBeGreaterThan(0);

    $this->seed(DatabaseSeeder::class);

    expect(tableCounts())->toBe($first);
});
