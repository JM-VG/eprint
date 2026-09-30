<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleSource;
use App\Models\GcashPayment;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalkinItem;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    Setting::create(['key' => 'commission_rate', 'value' => '10']);
    $this->maria = User::factory()->staff()->create(['username' => 'maria']);
    $this->main = Location::factory()->create(['slug' => 'main-it']);
    $this->ccs = Location::factory()->create(['slug' => 'ccs-office']);
    $this->bw = Service::factory()->create(['name' => 'Short Black & White', 'price' => '2.00']);
    $this->dir = storage_path('framework/testing/legacy');
    File::ensureDirectoryExists($this->dir);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function legacyCsv(array $rows): string
{
    $path = test()->dir.'/legacy-'.uniqid().'.csv';
    $lines = ['date,officer,location,service,quantity,sheets_used,amount,payment_method,reference_no,commission_rate'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }
    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}

test('it imports legacy walk-in sales with their original dates and line items', function () {
    $file = legacyCsv([
        ['2025-06-01 09:15', 'maria', 'main-it', 'Short Black & White', '10', '10', '20.00', 'cash', '', ''],
        ['2025-06-02 14:00', 'maria', 'ccs-office', '', '1', '3', '15.50', 'gcash', '1112223334445', '12.5'],
    ]);

    $this->artisan('sales:import-legacy', ['file' => $file])
        ->expectsOutputToContain('Imported 2 sale')
        ->assertSuccessful();

    $first = Sale::where('location_id', $this->main->id)->sole();
    expect($first->user_id)->toBe($this->maria->id)
        ->and($first->source)->toBe(SaleSource::Walkin)
        ->and($first->payment_method)->toBe(PaymentMethod::Cash)
        ->and($first->amount)->toBe('20.00')
        ->and($first->commission_rate)->toBe('10.00')
        ->and($first->commission_amount)->toBe('2.00')
        ->and($first->created_at->format('Y-m-d H:i'))->toBe('2025-06-01 09:15');

    $item = WalkinItem::where('sale_id', $first->id)->sole();
    expect($item->service_id)->toBe($this->bw->id)
        ->and($item->quantity)->toBe(10)
        ->and($item->sheets_used)->toBe(10)
        ->and($item->amount)->toBe('20.00');

    $second = Sale::where('location_id', $this->ccs->id)->sole();
    expect($second->commission_rate)->toBe('12.50')
        ->and($second->commission_amount)->toBe('1.94')
        ->and(WalkinItem::where('sale_id', $second->id)->sole()->service_id)->toBeNull()
        ->and(GcashPayment::sole()->reference_no)->toBe('1112223334445');
});

test('a dry run validates without saving', function () {
    $file = legacyCsv([['2025-06-01 09:15', 'maria', 'main-it', '', '1', '1', '5.00', 'cash', '', '']]);

    $this->artisan('sales:import-legacy', ['file' => $file, '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Sale::count())->toBe(0);
});

test('any invalid row stops the whole import and reports line numbers', function () {
    $file = legacyCsv([
        ['2025-06-01 09:15', 'maria', 'main-it', '', '1', '1', '5.00', 'cash', '', ''],
        ['not-a-date', 'ghost', 'nowhere', 'Unknown', '0', '-1', 'abc', 'card', '', '200'],
        ['2025-06-03 10:00', 'maria', 'main-it', '', '1', '1', '5.00', 'gcash', '', ''],
    ]);

    $this->artisan('sales:import-legacy', ['file' => $file])
        ->expectsOutputToContain('Line 3')
        ->expectsOutputToContain('Line 4')
        ->assertFailed();

    expect(Sale::count())->toBe(0);
});

test('importing the same file twice does not duplicate sales', function () {
    $file = legacyCsv([['2025-06-01 09:15', 'maria', 'main-it', '', '1', '1', '5.00', 'cash', '', '']]);

    $this->artisan('sales:import-legacy', ['file' => $file])->assertSuccessful();
    $this->artisan('sales:import-legacy', ['file' => $file])
        ->expectsOutputToContain('Skipped 1 duplicate')
        ->assertSuccessful();

    expect(Sale::count())->toBe(1);
});

test('genuinely identical sales in one file are all kept, and a re-run adds none', function () {
    $file = legacyCsv([
        ['2025-06-01 09:15', 'maria', 'main-it', '', '1', '1', '5.00', 'cash', '', ''],
        ['2025-06-01 09:15', 'maria', 'main-it', '', '1', '1', '5.00', 'cash', '', ''],
    ]);

    $this->artisan('sales:import-legacy', ['file' => $file])
        ->expectsOutputToContain('Imported 2 sale')
        ->assertSuccessful();

    $this->artisan('sales:import-legacy', ['file' => $file])
        ->expectsOutputToContain('Imported 0 sale(s). Skipped 2 duplicate(s).')
        ->assertSuccessful();

    expect(Sale::count())->toBe(2);
});

test('re-running a file with GCash rows skips them instead of failing', function () {
    $file = legacyCsv([
        ['2025-06-02 14:00', 'maria', 'ccs-office', '', '1', '3', '15.50', 'gcash', '1112223334445', ''],
        ['2025-06-02 15:00', 'maria', 'ccs-office', '', '1', '1', '8.00', 'cash', '', ''],
    ]);

    $this->artisan('sales:import-legacy', ['file' => $file])->assertSuccessful();
    $this->artisan('sales:import-legacy', ['file' => $file])
        ->expectsOutputToContain('Skipped 2 duplicate')
        ->assertSuccessful();

    expect(Sale::count())->toBe(2)
        ->and(GcashPayment::count())->toBe(1);
});

test('a new GCash row is imported even when an already-imported GCash row shares its officer, amount and minute', function () {
    $first = legacyCsv([['2025-06-02 14:00', 'maria', 'ccs-office', '', '1', '1', '20.00', 'gcash', 'REF-A', '']]);
    $this->artisan('sales:import-legacy', ['file' => $first])->assertSuccessful();

    $extended = legacyCsv([
        ['2025-06-02 14:00', 'maria', 'ccs-office', '', '1', '1', '20.00', 'gcash', 'REF-B', ''],
        ['2025-06-02 14:00', 'maria', 'ccs-office', '', '1', '1', '20.00', 'gcash', 'REF-A', ''],
    ]);

    $this->artisan('sales:import-legacy', ['file' => $extended])
        ->expectsOutputToContain('Imported 1 sale(s). Skipped 1 duplicate(s).')
        ->assertSuccessful();

    expect(Sale::count())->toBe(2)
        ->and(GcashPayment::pluck('reference_no')->sort()->values()->all())->toBe(['REF-A', 'REF-B']);
});

test('a GCash reference already used by a different sale is an error', function () {
    GcashPayment::factory()->create(['reference_no' => '5550001112223']);
    $file = legacyCsv([['2025-06-02 14:00', 'maria', 'ccs-office', '', '1', '3', '15.50', 'gcash', '5550001112223', '']]);

    $this->artisan('sales:import-legacy', ['file' => $file])
        ->expectsOutputToContain('already used by a different sale')
        ->assertFailed();
});

test('a reference repeated inside the file is an error', function () {
    $file = legacyCsv([
        ['2025-06-02 14:00', 'maria', 'ccs-office', '', '1', '3', '15.50', 'gcash', '777', ''],
        ['2025-06-02 15:00', 'maria', 'ccs-office', '', '1', '3', '9.00', 'gcash', '777', ''],
    ]);

    $this->artisan('sales:import-legacy', ['file' => $file])
        ->expectsOutputToContain('repeats line 2')
        ->assertFailed();

    expect(Sale::count())->toBe(0);
});

test('it rejects a missing file or wrong header', function () {
    $this->artisan('sales:import-legacy', ['file' => $this->dir.'/missing.csv'])->assertFailed();

    $bad = $this->dir.'/bad.csv';
    file_put_contents($bad, "foo,bar\n1,2\n");
    $this->artisan('sales:import-legacy', ['file' => $bad])
        ->expectsOutputToContain('header')
        ->assertFailed();
});
