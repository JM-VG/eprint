<?php

use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleSource;
use App\Models\GcashPayment;
use App\Models\JobLog;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StationSession;
use App\Models\User;
use App\Services\CommissionService;

beforeEach(function () {
    Setting::create(['key' => 'commission_rate', 'value' => '10']);
    $this->location = Location::factory()->create();
    $this->staff = User::factory()->staff()->create();
    StationSession::factory()->for($this->staff)->for($this->location)->create(['last_seen_at' => now()]);
    $this->student = User::factory()->create();
    $this->job = PrintJob::factory()->for($this->location)->create([
        'user_id' => $this->student->id,
        'claim_code' => 'EP-CLM2',
        'status' => JobStatus::Ready,
        'estimated_total' => '40.00',
        'final_total' => '45.50',
    ]);
});

describe('claim screen', function () {
    it('looks up a ready job by claim code and shows the final total', function () {
        $this->actingAs($this->staff)
            ->get(route('staff.claim', ['code' => 'ep-clm2']))
            ->assertOk()
            ->assertSee('EP-CLM2')
            ->assertSee('45.50')
            ->assertSee($this->student->name)
            ->assertSee('name="payment_method"', false);
    });

    it('explains when the code is unknown or the job is not ready', function () {
        $this->actingAs($this->staff)->get(route('staff.claim', ['code' => 'EP-NONE']))->assertSee('No job found');

        $this->job->forceFill(['status' => JobStatus::Printing])->save();
        $this->actingAs($this->staff)->get(route('staff.claim', ['code' => 'EP-CLM2']))->assertSee('not ready');
    });

    it('needs staff with an open station', function () {
        $this->actingAs(User::factory()->create())->get(route('staff.claim'))->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get(route('staff.claim'))->assertRedirect(route('staff.station'));
    });
});

describe('claiming', function () {
    it('claims with cash: job claimed and paid, sale created with commission', function () {
        $this->freezeSecond();

        $this->actingAs($this->staff)
            ->post(route('staff.claim.store'), ['code' => 'EP-CLM2', 'payment_method' => 'cash'])
            ->assertRedirect(route('staff.claim'))
            ->assertSessionHas('success');

        $job = $this->job->fresh();
        expect($job->status)->toBe(JobStatus::Claimed)
            ->and($job->payment_status)->toBe(PaymentStatus::Paid)
            ->and($job->payment_method)->toBe(PaymentMethod::Cash)
            ->and($job->received_by)->toBe($this->staff->id)
            ->and($job->claimed_at->equalTo(now()))->toBeTrue();

        $sale = Sale::sole();
        expect($sale->print_job_id)->toBe($job->id)
            ->and($sale->user_id)->toBe($this->staff->id)
            ->and($sale->location_id)->toBe($this->location->id)
            ->and($sale->source)->toBe(SaleSource::Online)
            ->and($sale->payment_method)->toBe(PaymentMethod::Cash)
            ->and($sale->amount)->toBe('45.50')
            ->and($sale->commission_rate)->toBe('10.00')
            ->and($sale->commission_amount)->toBe('4.55')
            ->and(GcashPayment::count())->toBe(0);

        expect(JobLog::latest('id')->first()->to_status)->toBe(JobStatus::Claimed);
    });

    it('claims with GCash and stores the reference', function () {
        $this->actingAs($this->staff)
            ->post(route('staff.claim.store'), ['code' => 'EP-CLM2', 'payment_method' => 'gcash', 'reference_no' => ' 1234567890123 '])
            ->assertSessionHasNoErrors();

        $payment = GcashPayment::sole();
        expect($payment->reference_no)->toBe('1234567890123')
            ->and($payment->amount)->toBe('45.50')
            ->and($payment->sale_id)->toBe(Sale::sole()->id)
            ->and($payment->print_job_id)->toBe($this->job->id)
            ->and($payment->received_by)->toBe($this->staff->id)
            ->and(Sale::sole()->payment_method)->toBe(PaymentMethod::Gcash);
    });

    it('requires a GCash reference that was never used before', function () {
        $this->actingAs($this->staff)
            ->post(route('staff.claim.store'), ['code' => 'EP-CLM2', 'payment_method' => 'gcash'])
            ->assertSessionHasErrors('reference_no');

        GcashPayment::factory()->create(['reference_no' => '5550001112223']);
        $this->actingAs($this->staff)
            ->post(route('staff.claim.store'), ['code' => 'EP-CLM2', 'payment_method' => 'gcash', 'reference_no' => '5550001112223'])
            ->assertSessionHasErrors('reference_no');

        expect($this->job->fresh()->status)->toBe(JobStatus::Ready)
            ->and(Sale::where('print_job_id', $this->job->id)->count())->toBe(0);
    });

    it('refuses jobs that are not ready and codes that do not exist', function () {
        $this->job->forceFill(['status' => JobStatus::Printing])->save();

        $this->actingAs($this->staff)
            ->post(route('staff.claim.store'), ['code' => 'EP-CLM2', 'payment_method' => 'cash'])
            ->assertSessionHas('error');
        $this->actingAs($this->staff)
            ->post(route('staff.claim.store'), ['code' => 'EP-ZZZZ', 'payment_method' => 'cash'])
            ->assertSessionHasErrors('code');

        expect(Sale::count())->toBe(0);
    });

    it('cannot claim the same job twice', function () {
        $this->actingAs($this->staff)->post(route('staff.claim.store'), ['code' => 'EP-CLM2', 'payment_method' => 'cash']);
        $this->actingAs($this->staff)
            ->post(route('staff.claim.store'), ['code' => 'EP-CLM2', 'payment_method' => 'cash'])
            ->assertSessionHas('error');

        expect(Sale::count())->toBe(1);
    });

    it('validates the payment method', function () {
        $this->actingAs($this->staff)
            ->post(route('staff.claim.store'), ['code' => 'EP-CLM2', 'payment_method' => 'card'])
            ->assertSessionHasErrors('payment_method');
    });
});

describe('credit rules', function () {
    it('credits an online student job to the staff who received payment', function () {
        app(CommissionService::class)->claim($this->job, $this->staff, PaymentMethod::Cash);

        expect(Sale::sole()->user_id)->toBe($this->staff->id)
            ->and(Sale::sole()->source)->toBe(SaleSource::Online);
    });

    it('credits a staff_job to the staff who submitted it, not the receiver', function () {
        $submitter = User::factory()->staff()->create();
        $this->job->forceFill(['user_id' => $submitter->id, 'source' => SaleSource::StaffJob])->save();

        app(CommissionService::class)->claim($this->job, $this->staff, PaymentMethod::Cash);

        expect(Sale::sole()->user_id)->toBe($submitter->id)
            ->and(Sale::sole()->source)->toBe(SaleSource::StaffJob)
            ->and($this->job->fresh()->received_by)->toBe($this->staff->id);
    });
});

describe('commission', function () {
    it('rounds commission half up to two decimals', function (string $amount, string $rate, string $expected) {
        expect(app(CommissionService::class)->commission($amount, $rate))->toBe($expected);
    })->with([
        ['45.50', '10', '4.55'],
        ['33.33', '7.5', '2.50'],
        ['10.00', '12.25', '1.23'],
        ['0.05', '10', '0.01'],
        ['0.04', '10', '0.00'],
        ['100.00', '0', '0.00'],
    ]);

    it('snapshots the rate so later changes do not touch old sales', function () {
        app(CommissionService::class)->claim($this->job, $this->staff, PaymentMethod::Cash);
        Setting::where('key', 'commission_rate')->first()->update(['value' => '25']);

        expect(Sale::sole()->commission_rate)->toBe('10.00')
            ->and(Sale::sole()->commission_amount)->toBe('4.55');
    });

    it('falls back to the estimated total when no final total was set', function () {
        $this->job->forceFill(['final_total' => null])->save();

        app(CommissionService::class)->claim($this->job, $this->staff, PaymentMethod::Cash);

        expect(Sale::sole()->amount)->toBe('40.00');
    });
});

it('rolls everything back if any step fails', function () {
    GcashPayment::factory()->create(['reference_no' => 'DUPLICATE01']);

    expect(fn () => app(CommissionService::class)->claim($this->job, $this->staff, PaymentMethod::Gcash, 'DUPLICATE01'))
        ->toThrow(Exception::class);

    expect($this->job->fresh()->status)->toBe(JobStatus::Ready)
        ->and($this->job->fresh()->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and(Sale::where('print_job_id', $this->job->id)->count())->toBe(0)
        ->and(JobLog::where('to_status', JobStatus::Claimed)->count())->toBe(0);
});
