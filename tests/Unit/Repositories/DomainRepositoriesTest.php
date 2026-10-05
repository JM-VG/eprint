<?php

use App\Firebase\ArrayDatabaseClient;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CommissionPayoutRepository;
use App\Repositories\GcashPaymentRepository;
use App\Repositories\JobLogRepository;
use App\Repositories\LocationRepository;
use App\Repositories\PrintJobRepository;
use App\Repositories\SaleRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SettingRepository;
use App\Repositories\StationSessionRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalkinItemRepository;
use Illuminate\Support\Carbon;

function domainDatabase(): ArrayDatabaseClient
{
    return new ArrayDatabaseClient;
}

it('finds locations by slug and keeps id order', function () {
    $database = domainDatabase();
    $locations = new LocationRepository($database);
    $locations->create(['name' => 'Main IT Printing Room', 'slug' => 'main-it']);
    $locations->create(['name' => 'CCS Office', 'slug' => 'ccs-office']);

    expect($locations->findBySlug('ccs-office')['name'])->toBe('CCS Office')
        ->and($locations->ordered())->toHaveCount(2)
        ->and($locations->ordered()[0]['slug'])->toBe('main-it');
});

it('filters services by active state and type', function () {
    $services = new ServiceRepository(domainDatabase());
    $services->create(['type' => 'document', 'name' => 'Short B&W', 'is_active' => true]);
    $services->create(['type' => 'document', 'name' => 'Retired', 'is_active' => false]);
    $services->create(['type' => 'photo', 'name' => '1x1', 'is_active' => true]);

    expect($services->active())->toHaveCount(2)
        ->and($services->activeOfType('document'))->toHaveCount(1)
        ->and($services->activeOfType('photo')[0]['name'])->toBe('1x1');
});

it('finds users by login, student id case-insensitively and username', function () {
    $users = new UserRepository(domainDatabase());
    $users->create(['student_id' => 'MMC2024-00123', 'role' => 'student', 'status' => 'approved']);
    $users->create(['username' => 'boss', 'role' => 'admin', 'status' => 'approved']);

    expect($users->findByStudentIdInsensitive('mmc2024-00123'))->not->toBeNull()
        ->and($users->findByLogin('MMC2024-00123')['role'])->toBe('student')
        ->and($users->findByLogin('boss')['role'])->toBe('admin')
        ->and($users->findByLogin('nobody'))->toBeNull();
});

it('lists pending students, approved admins and staff', function () {
    $users = new UserRepository(domainDatabase());
    $users->create(['student_id' => 'AAA2024-00001', 'role' => 'student', 'status' => 'pending']);
    $users->create(['student_id' => 'BBB2024-00002', 'role' => 'student', 'status' => 'approved']);
    $users->create(['username' => 'admin', 'role' => 'admin', 'status' => 'approved']);
    $users->create(['username' => 'staff', 'role' => 'staff', 'status' => 'approved']);

    expect($users->pendingStudents())->toHaveCount(1)
        ->and($users->approvedAdmins())->toHaveCount(1)
        ->and($users->staffAndAdmins())->toHaveCount(2);
});

it('finds open station sessions', function () {
    $sessions = new StationSessionRepository(domainDatabase());
    $sessions->create(['user_id' => 1, 'location_id' => 1, 'opened_at' => '2026-10-05T08:00:00+08:00', 'last_seen_at' => '2026-10-05T08:00:00+08:00', 'closed_at' => null, 'close_reason' => null]);
    $sessions->create(['user_id' => 2, 'location_id' => 1, 'opened_at' => '2026-10-05T07:00:00+08:00', 'last_seen_at' => '2026-10-05T07:00:00+08:00', 'closed_at' => '2026-10-05T07:30:00+08:00', 'close_reason' => 'manual']);

    expect($sessions->open())->toHaveCount(1)
        ->and($sessions->openForUser(1))->not->toBeNull()
        ->and($sessions->openForUser(2))->toBeNull()
        ->and($sessions->openAtLocation(1))->toHaveCount(1);
});

it('finds print jobs by claim code and status', function () {
    $jobs = new PrintJobRepository(domainDatabase());
    $jobs->create(['claim_code' => 'EP-ABCD', 'user_id' => 1, 'location_id' => 1, 'status' => 'pending']);
    $jobs->create(['claim_code' => 'EP-EFGH', 'user_id' => 1, 'location_id' => 1, 'status' => 'ready']);

    expect($jobs->findByClaimCode('ep-abcd')['status'])->toBe('pending')
        ->and($jobs->forUser(1))->toHaveCount(2)
        ->and($jobs->withStatusIn(['pending', 'ready']))->toHaveCount(2)
        ->and($jobs->withStatus('ready'))->toHaveCount(1);
});

it('finds unpaid sales for a staff member', function () {
    $sales = new SaleRepository(domainDatabase());
    $sales->create(['user_id' => 1, 'amount' => '10.00', 'payout_id' => null]);
    $sales->create(['user_id' => 1, 'amount' => '5.00', 'payout_id' => 9]);
    $sales->create(['user_id' => 2, 'amount' => '7.00', 'payout_id' => null]);

    expect($sales->unpaidForUser(1))->toHaveCount(1)
        ->and($sales->forUser(1))->toHaveCount(2);
});

it('finds walk-in items, gcash payments, payouts and job logs', function () {
    $database = domainDatabase();

    (new WalkinItemRepository($database))->create(['sale_id' => 1, 'quantity' => 2, 'sheets_used' => 2]);
    (new GcashPaymentRepository($database))->create(['reference_no' => 'REF-1', 'amount' => '25.00', 'sale_id' => 1]);
    (new CommissionPayoutRepository($database))->create(['user_id' => 3, 'amount' => '12.00']);
    (new JobLogRepository($database))->create(['print_job_id' => 4, 'to_status' => 'accepted']);

    expect((new WalkinItemRepository($database))->forSale(1))->toHaveCount(1)
        ->and((new GcashPaymentRepository($database))->findByReference('REF-1')['amount'])->toBe('25.00')
        ->and((new GcashPaymentRepository($database))->forSale(1))->not->toBeNull()
        ->and((new CommissionPayoutRepository($database))->forStaff(3))->toHaveCount(1)
        ->and((new JobLogRepository($database))->forJob(4))->toHaveCount(1);
});

it('filters announcements by their active window', function () {
    $announcements = new AnnouncementRepository(domainDatabase());
    $announcements->create(['title' => 'Always', 'starts_at' => null, 'ends_at' => null]);
    $announcements->create(['title' => 'Future', 'starts_at' => Carbon::now()->addDay()->toIso8601String(), 'ends_at' => null]);
    $announcements->create(['title' => 'Past', 'starts_at' => null, 'ends_at' => Carbon::now()->subDay()->toIso8601String()]);

    $active = $announcements->active();

    expect($active)->toHaveCount(1)
        ->and($active[0]['title'])->toBe('Always');
});

it('reads and writes key-value settings without overwriting edits', function () {
    $settings = new SettingRepository(domainDatabase());

    $settings->firstOrCreateMany(['commission_rate' => '0', 'retention_days' => '7']);

    expect($settings->get('retention_days'))->toBe('7')
        ->and($settings->all())->toHaveKeys(['commission_rate', 'retention_days']);

    $settings->set('commission_rate', '5');
    $settings->firstOrCreateMany(['commission_rate' => '0', 'gcash_name' => '']);

    expect($settings->get('commission_rate'))->toBe('5')
        ->and($settings->has('gcash_name'))->toBeTrue();

    $settings->forget('gcash_name');

    expect($settings->has('gcash_name'))->toBeFalse();
});
