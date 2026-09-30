<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleSource;
use App\Models\GcashPayment;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StationSession;
use App\Models\User;
use App\Models\WalkinItem;
use App\Services\CommissionService;

beforeEach(function () {
    Setting::create(['key' => 'commission_rate', 'value' => '10']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
    $this->staff = User::factory()->staff()->create();
    StationSession::factory()->for($this->staff)->for($this->ccs)->create(['last_seen_at' => now()]);
    $this->bw = Service::factory()->create(['name' => 'Short Black & White', 'price' => '2.00']);
    $this->photo = Service::factory()->photo()->create(['name' => 'Passport', 'price' => '45.00']);
});

function walkinData(array $overrides = []): array
{
    return array_merge([
        'items' => [
            ['service_id' => test()->bw->id, 'quantity' => 10, 'sheets_used' => 10, 'amount' => '20.00'],
        ],
        'payment_method' => 'cash',
    ], $overrides);
}

test('the form lists active services with prices for prefilling', function () {
    Service::factory()->inactive()->create(['name' => 'Retired']);

    $this->actingAs($this->staff)
        ->get(route('staff.walkin.create'))
        ->assertOk()
        ->assertSee('Short Black &amp; White', false)
        ->assertSee('data-price="2.00"', false)
        ->assertSee('Passport')
        ->assertDontSee('Retired')
        ->assertSee('name="items[0][amount]"', false);
});

test('a walk-in sale is credited to the staff who entered it at their station', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.walkin.store'), walkinData())
        ->assertRedirect(route('staff.walkin.create'))
        ->assertSessionHas('success');

    $sale = Sale::sole();
    expect($sale->user_id)->toBe($this->staff->id)
        ->and($sale->location_id)->toBe($this->ccs->id)
        ->and($sale->source)->toBe(SaleSource::Walkin)
        ->and($sale->print_job_id)->toBeNull()
        ->and($sale->payment_method)->toBe(PaymentMethod::Cash)
        ->and($sale->amount)->toBe('20.00')
        ->and($sale->commission_rate)->toBe('10.00')
        ->and($sale->commission_amount)->toBe('2.00');

    $item = WalkinItem::sole();
    expect($item->sale_id)->toBe($sale->id)
        ->and($item->service_id)->toBe($this->bw->id)
        ->and($item->quantity)->toBe(10)
        ->and($item->sheets_used)->toBe(10)
        ->and($item->unit_price)->toBe('2.00')
        ->and($item->amount)->toBe('20.00');
});

test('several line items add up, and amounts can be edited from the price list', function () {
    $this->actingAs($this->staff)->post(route('staff.walkin.store'), walkinData([
        'items' => [
            ['service_id' => $this->bw->id, 'quantity' => 30, 'sheets_used' => 15, 'amount' => '55.00'],
            ['service_id' => $this->photo->id, 'quantity' => 2, 'sheets_used' => 1, 'amount' => '90.00'],
        ],
    ]))->assertSessionHasNoErrors();

    expect(Sale::sole()->amount)->toBe('145.00')
        ->and(Sale::sole()->commission_amount)->toBe('14.50')
        ->and(WalkinItem::count())->toBe(2)
        ->and(WalkinItem::where('service_id', $this->bw->id)->sole()->amount)->toBe('55.00')
        ->and(WalkinItem::where('service_id', $this->bw->id)->sole()->unit_price)->toBe('2.00');
});

test('GCash walk-ins need a unique reference and store a payment row', function () {
    $this->actingAs($this->staff)
        ->post(route('staff.walkin.store'), walkinData(['payment_method' => 'gcash']))
        ->assertSessionHasErrors('reference_no');

    $this->actingAs($this->staff)
        ->post(route('staff.walkin.store'), walkinData(['payment_method' => 'gcash', 'reference_no' => '9990001112223']))
        ->assertSessionHasNoErrors();

    $payment = GcashPayment::sole();
    expect($payment->sale_id)->toBe(Sale::sole()->id)
        ->and($payment->print_job_id)->toBeNull()
        ->and($payment->amount)->toBe('20.00')
        ->and($payment->received_by)->toBe($this->staff->id);

    $this->actingAs($this->staff)
        ->post(route('staff.walkin.store'), walkinData(['payment_method' => 'gcash', 'reference_no' => '9990001112223']))
        ->assertSessionHasErrors('reference_no');

    expect(Sale::count())->toBe(1);
});

test('line items are validated', function (array $items, string $field) {
    $this->actingAs($this->staff)
        ->post(route('staff.walkin.store'), walkinData(['items' => $items]))
        ->assertSessionHasErrors($field);

    expect(Sale::count())->toBe(0);
})->with([
    'no items' => [[], 'items'],
    'quantity 0' => [[['service_id' => 1, 'quantity' => 0, 'sheets_used' => 0, 'amount' => '1.00']], 'items.0.quantity'],
    'negative amount' => [[['service_id' => 1, 'quantity' => 1, 'sheets_used' => 1, 'amount' => '-1']], 'items.0.amount'],
    'negative sheets' => [[['service_id' => 1, 'quantity' => 1, 'sheets_used' => -1, 'amount' => '1']], 'items.0.sheets_used'],
    'unknown service' => [[['service_id' => 9999, 'quantity' => 1, 'sheets_used' => 1, 'amount' => '1']], 'items.0.service_id'],
]);

test('inactive services cannot be sold', function () {
    $retired = Service::factory()->inactive()->create();

    $this->actingAs($this->staff)
        ->post(route('staff.walkin.store'), walkinData(['items' => [
            ['service_id' => $retired->id, 'quantity' => 1, 'sheets_used' => 1, 'amount' => '1.00'],
        ]]))
        ->assertSessionHasErrors('items.0.service_id');
});

test('walk-ins need staff with an open station', function () {
    $this->actingAs(User::factory()->create())->post(route('staff.walkin.store'), walkinData())->assertForbidden();
    $this->actingAs(User::factory()->staff()->create())->post(route('staff.walkin.store'), walkinData())->assertRedirect(route('staff.station'));

    expect(Sale::count())->toBe(0);
});

test('a failed walk-in saves nothing', function () {
    GcashPayment::factory()->create(['reference_no' => 'TAKEN000001']);
    $before = Sale::count();

    expect(fn () => app(CommissionService::class)->recordWalkin(
        $this->staff, $this->ccs->id,
        [['service_id' => $this->bw->id, 'quantity' => 1, 'sheets_used' => 1, 'amount' => '2.00']],
        PaymentMethod::Gcash, 'TAKEN000001',
    ))->toThrow(Exception::class);

    expect(Sale::count())->toBe($before)
        ->and(WalkinItem::count())->toBe(0);
});
