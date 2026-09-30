<?php

use App\Models\CommissionPayout;
use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use App\Services\PayoutService;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create(['name' => 'Maria Santos']);
    $this->other = User::factory()->staff()->create();
    $this->location = Location::factory()->create();
});

function payoutSale(User $user, string $commission, string $date, array $attributes = []): Sale
{
    return Sale::factory()->create(array_merge([
        'user_id' => $user->id,
        'location_id' => test()->location->id,
        'commission_amount' => $commission,
        'created_at' => $date,
    ], $attributes));
}

function payoutData(array $overrides = []): array
{
    return array_merge([
        'user_id' => test()->staff->id,
        'from' => '2026-09-01',
        'to' => '2026-09-30',
        'notes' => 'September payout',
    ], $overrides);
}

test('only admins manage payouts', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.payouts.index'))->assertForbidden();
    $this->actingAs(User::factory()->{$state}()->create())->post(route('admin.payouts.store'), payoutData())->assertForbidden();
})->with(['student', 'staff']);

test('the preview sums unpaid commission for the staff member and range', function () {
    payoutSale($this->staff, '10.00', '2026-09-05 10:00:00');
    payoutSale($this->staff, '2.50', '2026-09-30 23:59:00');
    payoutSale($this->staff, '99.00', '2026-10-01 00:00:00');
    payoutSale($this->other, '50.00', '2026-09-10 10:00:00');

    $this->actingAs($this->admin)
        ->get(route('admin.payouts.index', payoutData()))
        ->assertOk()
        ->assertViewHas('preview', fn (array $p) => $p['amount'] === '12.50' && $p['count'] === 2)
        ->assertSee('12.50');
});

test('creating a payout records it and links exactly the unpaid sales in range', function () {
    $this->freezeSecond();
    $a = payoutSale($this->staff, '10.00', '2026-09-05 10:00:00');
    $b = payoutSale($this->staff, '2.50', '2026-09-20 10:00:00');
    $outside = payoutSale($this->staff, '7.00', '2026-08-31 23:59:00');
    $otherStaff = payoutSale($this->other, '50.00', '2026-09-10 10:00:00');
    $earlier = CommissionPayout::factory()->create(['user_id' => $this->staff->id]);
    $alreadyPaid = payoutSale($this->staff, '4.00', '2026-09-15 10:00:00', ['payout_id' => $earlier->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.payouts.store'), payoutData())
        ->assertRedirect()
        ->assertSessionHas('success');

    $payout = CommissionPayout::latest('id')->first();
    expect($payout->user_id)->toBe($this->staff->id)
        ->and($payout->paid_by)->toBe($this->admin->id)
        ->and($payout->amount)->toBe('12.50')
        ->and($payout->period_start->toDateString())->toBe('2026-09-01')
        ->and($payout->period_end->toDateString())->toBe('2026-09-30')
        ->and($payout->paid_at->equalTo(now()))->toBeTrue()
        ->and($payout->notes)->toBe('September payout');

    expect($a->fresh()->payout_id)->toBe($payout->id)
        ->and($b->fresh()->payout_id)->toBe($payout->id)
        ->and($outside->fresh()->payout_id)->toBeNull()
        ->and($otherStaff->fresh()->payout_id)->toBeNull()
        ->and($alreadyPaid->fresh()->payout_id)->toBe($earlier->id);
});

test('a payout with nothing unpaid is refused', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.payouts.store'), payoutData())
        ->assertSessionHas('error');

    expect(CommissionPayout::count())->toBe(0);
});

test('a payout whose unpaid sales sum to zero commission is refused', function () {
    $zero = payoutSale($this->staff, '0.00', '2026-09-05 10:00:00');

    $this->actingAs($this->admin)
        ->post(route('admin.payouts.store'), payoutData())
        ->assertSessionHas('error');

    expect(CommissionPayout::count())->toBe(0)
        ->and($zero->fresh()->payout_id)->toBeNull();
});

test('the preview total is exact', function () {
    payoutSale($this->staff, '0.10', '2026-09-05 10:00:00');
    payoutSale($this->staff, '0.20', '2026-09-06 10:00:00');

    $this->actingAs($this->admin)
        ->get(route('admin.payouts.index', payoutData()))
        ->assertViewHas('preview', fn (array $p) => $p['amount'] === '0.30' && $p['count'] === 2);
});

test('submitting twice does not pay the same sales twice', function () {
    payoutSale($this->staff, '10.00', '2026-09-05 10:00:00');

    $this->actingAs($this->admin)->post(route('admin.payouts.store'), payoutData());
    $this->actingAs($this->admin)->post(route('admin.payouts.store'), payoutData())->assertSessionHas('error');

    expect(CommissionPayout::count())->toBe(1);
});

test('the staff unpaid balance drops after a payout', function () {
    payoutSale($this->staff, '10.00', now()->subDay()->toDateTimeString());

    app(PayoutService::class)->create($this->staff, $this->admin, now()->subMonth()->toDateString(), now()->toDateString());

    $this->actingAs($this->staff)
        ->get(route('staff.commission'))
        ->assertViewHas('summary', fn (array $s) => $s['unpaid'] === '0.00');
});

test('payout input is validated', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->post(route('admin.payouts.store'), payoutData($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'to before from' => [['from' => '2026-09-10', 'to' => '2026-09-01'], 'to'],
    'missing staff' => [['user_id' => null], 'user_id'],
    'bad date' => [['from' => 'yesterday'], 'from'],
]);

test('students cannot receive payouts', function () {
    $student = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.payouts.store'), payoutData(['user_id' => $student->id]))
        ->assertSessionHasErrors('user_id');
});

test('the payouts page lists past payouts and a payout shows its sales', function () {
    $sale = payoutSale($this->staff, '10.00', '2026-09-05 10:00:00');
    $payout = app(PayoutService::class)->create($this->staff, $this->admin, '2026-09-01', '2026-09-30');

    $this->actingAs($this->admin)
        ->get(route('admin.payouts.index'))
        ->assertSee('Maria Santos')
        ->assertSee('10.00')
        ->assertSee(route('admin.payouts.show', $payout), false);

    $this->actingAs($this->admin)
        ->get(route('admin.payouts.show', $payout))
        ->assertOk()
        ->assertViewHas('payout', fn ($p) => $p->sales->pluck('id')->all() === [$sale->id]);
});
