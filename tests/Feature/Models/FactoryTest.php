<?php

use App\Enums\CloseReason;
use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SaleSource;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\CommissionPayout;
use App\Models\GcashPayment;
use App\Models\JobLog;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StationSession;
use App\Models\User;
use App\Models\WalkinItem;

it('creates users with role and status states', function () {
    $student = User::factory()->create();
    $pending = User::factory()->pending()->create();
    $staff = User::factory()->staff()->create();
    $admin = User::factory()->admin()->create();

    expect($student->role)->toBe(Role::Student)
        ->and($student->status)->toBe(UserStatus::Approved)
        ->and($student->student_id)->toMatch('/^[A-Z]{3}\d{4}-\d{5}$/')
        ->and($student->username)->toBeNull()
        ->and($pending->status)->toBe(UserStatus::Pending)
        ->and($staff->role)->toBe(Role::Staff)
        ->and($staff->username)->not->toBeNull()
        ->and($staff->student_id)->toBeNull()
        ->and($admin->role)->toBe(Role::Admin)
        ->and($admin->must_change_password)->toBeFalse();
});

it('links an approved user to the admin who approved them', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['approved_by' => $admin->id, 'approved_at' => now()]);

    expect($student->approver->is($admin))->toBeTrue()
        ->and($student->approved_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('creates station sessions with user and location', function () {
    $session = StationSession::factory()->create();
    $closed = StationSession::factory()->closed(CloseReason::Timeout)->create();

    expect($session->user)->toBeInstanceOf(User::class)
        ->and($session->user->role)->toBe(Role::Staff)
        ->and($session->location)->toBeInstanceOf(Location::class)
        ->and($session->closed_at)->toBeNull()
        ->and($session->location->stationSessions)->toHaveCount(1)
        ->and($session->user->stationSessions)->toHaveCount(1)
        ->and($closed->close_reason)->toBe(CloseReason::Timeout)
        ->and($closed->closed_at)->not->toBeNull();
});

it('creates document and photo services', function () {
    $document = Service::factory()->document()->create();
    $photo = Service::factory()->photo()->create();

    expect($document->type)->toBe('document')
        ->and($document->unit)->toBe('page')
        ->and($photo->type)->toBe('photo')
        ->and($photo->unit)->toBe('set')
        ->and($photo->pieces_per_set)->toBeGreaterThan(0)
        ->and($document->is_active)->toBeTrue();
});

it('creates print jobs with every relation and enum cast', function () {
    $job = PrintJob::factory()->create();

    expect($job->user)->toBeInstanceOf(User::class)
        ->and($job->location)->toBeInstanceOf(Location::class)
        ->and($job->service)->toBeInstanceOf(Service::class)
        ->and($job->status)->toBe(JobStatus::Pending)
        ->and($job->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($job->source)->toBe(SaleSource::Online)
        ->and($job->claim_code)->toMatch('/^EP-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}$/')
        ->and($job->duplex)->toBeBool()
        ->and($job->user->printJobs)->toHaveCount(1)
        ->and($job->location->printJobs)->toHaveCount(1)
        ->and($job->service->printJobs)->toHaveCount(1);
});

it('creates a claimed print job with receiver, logs, sale and gcash payment', function () {
    $job = PrintJob::factory()->claimed(PaymentMethod::Gcash)->create();
    JobLog::factory()->for($job)->create(['from_status' => JobStatus::Ready, 'to_status' => JobStatus::Claimed]);
    $sale = Sale::factory()->for($job)->create();
    GcashPayment::factory()->for($job)->for($sale)->create();

    $job->refresh();

    expect($job->status)->toBe(JobStatus::Claimed)
        ->and($job->payment_status)->toBe(PaymentStatus::Paid)
        ->and($job->payment_method)->toBe(PaymentMethod::Gcash)
        ->and($job->receiver)->toBeInstanceOf(User::class)
        ->and($job->logs)->toHaveCount(1)
        ->and($job->logs->first()->to_status)->toBe(JobStatus::Claimed)
        ->and($job->sale->is($sale))->toBeTrue()
        ->and($job->gcashPayment->reference_no)->not->toBeEmpty()
        ->and($job->receiver->receivedJobs)->toHaveCount(1);
});

it('creates job logs with job and actor', function () {
    $log = JobLog::factory()->create();

    expect($log->printJob)->toBeInstanceOf(PrintJob::class)
        ->and($log->user)->toBeInstanceOf(User::class)
        ->and($log->to_status)->toBeInstanceOf(JobStatus::class);
});

it('creates sales with credited staff, location, commission and payout', function () {
    $sale = Sale::factory()->create(['amount' => 200, 'commission_rate' => 10, 'commission_amount' => 20]);

    expect($sale->user)->toBeInstanceOf(User::class)
        ->and($sale->location)->toBeInstanceOf(Location::class)
        ->and($sale->source)->toBe(SaleSource::Online)
        ->and($sale->payment_method)->toBe(PaymentMethod::Cash)
        ->and($sale->amount)->toBe('200.00')
        ->and($sale->commission_amount)->toBe('20.00')
        ->and($sale->payout)->toBeNull()
        ->and($sale->user->sales)->toHaveCount(1)
        ->and($sale->location->sales)->toHaveCount(1);

    $payout = CommissionPayout::factory()->create(['user_id' => $sale->user_id]);
    $sale->update(['payout_id' => $payout->id]);

    expect($sale->fresh()->payout->is($payout))->toBeTrue()
        ->and($payout->sales)->toHaveCount(1)
        ->and($payout->user->is($sale->user))->toBeTrue()
        ->and($payout->payer)->toBeInstanceOf(User::class)
        ->and($payout->user->payouts)->toHaveCount(1);
});

it('creates walk-in sales with line items', function () {
    $sale = Sale::factory()->walkin()->has(WalkinItem::factory()->count(2), 'items')->create();

    expect($sale->source)->toBe(SaleSource::Walkin)
        ->and($sale->printJob)->toBeNull()
        ->and($sale->items)->toHaveCount(2)
        ->and($sale->items->first()->service)->toBeInstanceOf(Service::class)
        ->and($sale->items->first()->sale->is($sale))->toBeTrue()
        ->and($sale->items->first()->service->walkinItems)->toHaveCount(1);
});

it('creates gcash payments with a unique reference', function () {
    $payments = GcashPayment::factory()->count(3)->create();

    expect($payments->pluck('reference_no')->unique())->toHaveCount(3)
        ->and($payments->first()->sale)->toBeInstanceOf(Sale::class)
        ->and($payments->first()->sale->gcashPayment->is($payments->first()))->toBeTrue()
        ->and($payments->first()->receiver)->toBeInstanceOf(User::class);
});

it('creates settings and announcements', function () {
    $setting = Setting::factory()->create(['key' => 'commission_rate', 'value' => '10']);
    $announcement = Announcement::factory()->create();

    expect($setting->value)->toBe('10')
        ->and($announcement->creator)->toBeInstanceOf(User::class)
        ->and($announcement->starts_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($announcement->ends_at->greaterThan($announcement->starts_at))->toBeTrue();
});
