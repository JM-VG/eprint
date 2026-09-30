<?php

use App\Enums\JobStatus;
use App\Models\CommissionPayout;
use App\Models\JobLog;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(15, 0)); // a Wednesday
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
    $this->staff = User::factory()->staff()->create();
});

function sale(array $attributes): Sale
{
    return Sale::factory()->create(array_merge([
        'user_id' => test()->staff->id,
        'location_id' => test()->main->id,
        'amount' => '100.00',
        'commission_rate' => '10.00',
        'commission_amount' => '10.00',
    ], $attributes));
}

test('the dashboard sums commission for today, this week, this month and all time', function () {
    sale(['commission_amount' => '1.00', 'created_at' => now()]);
    sale(['commission_amount' => '2.00', 'created_at' => now()->setDate(2026, 9, 28)]); // Monday this week
    sale(['commission_amount' => '4.00', 'created_at' => now()->setDate(2026, 9, 2)]);  // this month
    sale(['commission_amount' => '8.00', 'created_at' => now()->setDate(2026, 8, 15)]); // last month
    sale(['user_id' => User::factory()->staff()->create()->id, 'commission_amount' => '99.00']);

    $this->actingAs($this->staff)
        ->get(route('staff.commission'))
        ->assertOk()
        ->assertViewHas('summary', fn (array $s) => $s['today'] === '1.00'
            && $s['week'] === '3.00'
            && $s['month'] === '7.00'
            && $s['all_time'] === '15.00');
});

test('the unpaid balance excludes commission already paid out', function () {
    $payout = CommissionPayout::factory()->create(['user_id' => $this->staff->id]);
    sale(['commission_amount' => '5.00', 'payout_id' => $payout->id]);
    sale(['commission_amount' => '3.25']);
    sale(['commission_amount' => '1.50']);

    $this->actingAs($this->staff)
        ->get(route('staff.commission'))
        ->assertViewHas('summary', fn (array $s) => $s['unpaid'] === '4.75')
        ->assertSee('4.75');
});

test('jobs handled counts distinct jobs this staff member acted on', function () {
    $jobs = PrintJob::factory()->count(3)->create();
    foreach ($jobs as $job) {
        JobLog::factory()->for($job)->create(['user_id' => $this->staff->id, 'to_status' => JobStatus::Accepted]);
    }
    JobLog::factory()->for($jobs[0])->create(['user_id' => $this->staff->id, 'to_status' => JobStatus::Printing]);
    JobLog::factory()->create(['user_id' => User::factory()->staff()->create()->id]);

    $this->actingAs($this->staff)
        ->get(route('staff.commission'))
        ->assertViewHas('summary', fn (array $s) => $s['jobs_handled'] === 3);
});

test('the sales list shows only own sales, newest first', function () {
    sale(['amount' => '11.00', 'created_at' => now()->subDays(2)]);
    sale(['amount' => '22.00', 'created_at' => now()->subDay()]);
    sale(['user_id' => User::factory()->staff()->create()->id, 'amount' => '777.00']);

    $this->actingAs($this->staff)
        ->get(route('staff.commission'))
        ->assertSeeInOrder(['22.00', '11.00'])
        ->assertDontSee('777.00');
});

test('the sales list filters by date range and location', function () {
    $inRange = sale(['location_id' => $this->ccs->id, 'created_at' => now()->setDate(2026, 9, 10)]);
    sale(['location_id' => $this->main->id, 'created_at' => now()->setDate(2026, 9, 10)]);
    sale(['location_id' => $this->ccs->id, 'created_at' => now()->setDate(2026, 9, 20)]);

    $response = $this->actingAs($this->staff)->get(route('staff.commission', [
        'from' => '2026-09-01', 'to' => '2026-09-15', 'location_id' => $this->ccs->id,
    ]));

    expect($response->viewData('sales')->pluck('id')->all())->toBe([$inRange->id]);
});

test('filters are validated', function () {
    $this->actingAs($this->staff)
        ->get(route('staff.commission', ['from' => '2026-09-10', 'to' => '2026-09-01']))
        ->assertSessionHasErrors('to');
});

test('the dashboard does not need an open station and is staff only', function () {
    $this->actingAs($this->staff)->get(route('staff.commission'))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('staff.commission'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('staff.commission'))->assertForbidden();
});
