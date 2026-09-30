<?php

use App\Enums\SaleSource;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\User;
use App\Models\WalkinItem;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(15, 0)); // Wednesday
    $this->admin = User::factory()->admin()->create();
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
    $this->maria = User::factory()->staff()->create(['name' => 'Maria Santos']);
    $this->jose = User::factory()->staff()->create(['name' => 'Jose Reyes']);
});

function reportSale(array $attributes, int $jobSheets = 0, int $walkinSheets = 0): Sale
{
    $job = $jobSheets ? PrintJob::factory()->create(['sheets_used' => $jobSheets]) : null;

    $sale = Sale::factory()->create(array_merge([
        'user_id' => test()->maria->id,
        'location_id' => test()->main->id,
        'print_job_id' => $job?->id,
        'source' => $job ? SaleSource::Online : SaleSource::Walkin,
        'amount' => '100.00',
        'commission_rate' => '10.00',
        'commission_amount' => '10.00',
        'created_at' => now(),
    ], $attributes));

    if ($walkinSheets) {
        WalkinItem::factory()->for($sale)->create(['sheets_used' => $walkinSheets]);
    }

    return $sale;
}

test('only admins can see the report and export', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.reports.sales'))->assertForbidden();
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.reports.sales.export'))->assertForbidden();
})->with(['student', 'staff']);

test('totals for amount, commission and sheets used', function () {
    reportSale(['amount' => '45.50', 'commission_amount' => '4.55'], jobSheets: 12);
    reportSale(['amount' => '20.00', 'commission_amount' => '2.00'], walkinSheets: 7);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.sales', ['timeframe' => 'today']))
        ->assertOk()
        ->assertViewHas('totals', fn (array $t) => $t['amount'] === '65.50'
            && $t['commission'] === '6.55'
            && $t['sheets'] === 19
            && $t['count'] === 2)
        ->assertSee('65.50');
});

test('timeframes: today, this week, this month and a custom range', function (array $query, int $expected) {
    reportSale(['created_at' => now()]);                               // today (Wed)
    reportSale(['created_at' => now()->setDate(2026, 9, 28)]);         // Monday this week
    reportSale(['created_at' => now()->setDate(2026, 9, 2)]);          // this month
    reportSale(['created_at' => now()->setDate(2026, 8, 20)]);         // last month

    $this->actingAs($this->admin)
        ->get(route('admin.reports.sales', $query))
        ->assertViewHas('totals', fn (array $t) => $t['count'] === $expected);
})->with([
    'today' => [['timeframe' => 'today'], 1],
    'week' => [['timeframe' => 'week'], 2],
    'month' => [['timeframe' => 'month'], 3],
    'custom' => [['timeframe' => 'custom', 'from' => '2026-08-01', 'to' => '2026-09-02'], 2],
    'default is this month' => [[], 3],
]);

test('filters by officer, location and source', function () {
    reportSale(['user_id' => $this->jose->id, 'location_id' => $this->ccs->id, 'source' => SaleSource::Walkin]);
    reportSale(['user_id' => $this->jose->id, 'location_id' => $this->main->id, 'source' => SaleSource::Walkin]);
    reportSale(['user_id' => $this->maria->id, 'location_id' => $this->ccs->id, 'source' => SaleSource::Walkin]);
    reportSale(['user_id' => $this->jose->id, 'location_id' => $this->ccs->id, 'source' => SaleSource::StaffJob]);

    $response = $this->actingAs($this->admin)->get(route('admin.reports.sales', [
        'user_id' => $this->jose->id, 'location_id' => $this->ccs->id, 'source' => 'walkin',
    ]));

    $response->assertViewHas('totals', fn (array $t) => $t['count'] === 1);
});

test('filters are validated', function (array $query, string $field) {
    $this->actingAs($this->admin)
        ->get(route('admin.reports.sales', $query))
        ->assertSessionHasErrors($field);
})->with([
    'custom needs dates' => [['timeframe' => 'custom'], 'from'],
    'range backwards' => [['timeframe' => 'custom', 'from' => '2026-09-10', 'to' => '2026-09-01'], 'to'],
    'bad timeframe' => [['timeframe' => 'year'], 'timeframe'],
    'bad source' => [['source' => 'cash'], 'source'],
]);

test('the CSV export streams the filtered rows', function () {
    reportSale(['amount' => '45.50', 'commission_amount' => '4.55', 'location_id' => $this->ccs->id], jobSheets: 12);
    reportSale(['location_id' => $this->main->id]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.reports.sales.export', ['timeframe' => 'today', 'location_id' => $this->ccs->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('.csv');

    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $response->streamedContent()))));
    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('Date')->toContain('Officer')->toContain('Amount')->toContain('Commission')->toContain('Sheets')
        ->and($lines[1])->toContain('CCS Office')->toContain('Maria Santos')->toContain('45.50')->toContain('4.55')->toContain('12');
});

test('the CSV export neutralises spreadsheet formulas', function () {
    $evil = User::factory()->staff()->create(['name' => '=HYPERLINK("http://evil")']);
    reportSale(['user_id' => $evil->id]);

    $content = $this->actingAs($this->admin)
        ->get(route('admin.reports.sales.export', ['timeframe' => 'today']))
        ->streamedContent();

    expect($content)->toContain("'=HYPERLINK")
        ->and($content)->not->toContain(',=HYPERLINK');
});

test('the report page links to the export with the same filters', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.reports.sales', ['timeframe' => 'week', 'source' => 'online']))
        ->assertSee(e(route('admin.reports.sales.export', ['timeframe' => 'week', 'source' => 'online'])), false);
});
