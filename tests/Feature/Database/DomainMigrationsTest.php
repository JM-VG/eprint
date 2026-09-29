<?php

use Illuminate\Support\Facades\Schema;

function domainForeignKeys(string $table): array
{
    return collect(Schema::getForeignKeys($table))
        ->mapWithKeys(fn (array $fk) => [$fk['columns'][0] => $fk['foreign_table']])
        ->all();
}

function domainUniqueColumns(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->filter(fn (array $index) => $index['unique'] && count($index['columns']) === 1)
        ->pluck('columns.0')
        ->all();
}

it('creates the services price list table', function () {
    expect(Schema::hasColumns('services', [
        'type', 'name', 'paper_size', 'color_mode', 'unit', 'pieces_per_set', 'price', 'is_active',
    ]))->toBeTrue();
});

it('creates print_jobs with spec columns and foreign keys', function () {
    expect(Schema::hasColumns('print_jobs', [
        'claim_code', 'user_id', 'location_id', 'service_id', 'source', 'original_name', 'stored_path',
        'pdf_path', 'file_type', 'file_size', 'page_count', 'page_range', 'copies', 'duplex', 'sets',
        'notes', 'unit_price', 'estimated_total', 'final_total', 'sheets_used', 'status',
        'payment_method', 'payment_status', 'reject_reason', 'received_by', 'claimed_at', 'files_deleted_at',
    ]))->toBeTrue();

    expect(domainUniqueColumns('print_jobs'))->toContain('claim_code');
    expect(domainForeignKeys('print_jobs'))->toMatchArray([
        'user_id' => 'users',
        'location_id' => 'locations',
        'service_id' => 'services',
        'received_by' => 'users',
    ]);
});

it('creates gcash_payments with a unique reference number', function () {
    expect(Schema::hasColumns('gcash_payments', ['reference_no', 'amount', 'print_job_id', 'sale_id', 'received_by']))->toBeTrue();
    expect(domainUniqueColumns('gcash_payments'))->toContain('reference_no');
    expect(domainForeignKeys('gcash_payments'))->toMatchArray([
        'print_job_id' => 'print_jobs',
        'sale_id' => 'sales',
        'received_by' => 'users',
    ]);
});

it('creates sales with commission snapshot and payout link', function () {
    expect(Schema::hasColumns('sales', [
        'user_id', 'print_job_id', 'location_id', 'source', 'payment_method', 'amount',
        'commission_rate', 'commission_amount', 'payout_id',
    ]))->toBeTrue();
    expect(domainForeignKeys('sales'))->toMatchArray([
        'user_id' => 'users',
        'print_job_id' => 'print_jobs',
        'location_id' => 'locations',
        'payout_id' => 'commission_payouts',
    ]);
});

it('creates walkin_items linked to sales and services', function () {
    expect(Schema::hasColumns('walkin_items', ['sale_id', 'service_id', 'quantity', 'sheets_used', 'unit_price', 'amount']))->toBeTrue();
    expect(domainForeignKeys('walkin_items'))->toMatchArray([
        'sale_id' => 'sales',
        'service_id' => 'services',
    ]);
});

it('creates commission_payouts', function () {
    expect(Schema::hasColumns('commission_payouts', ['user_id', 'paid_by', 'period_start', 'period_end', 'amount', 'paid_at']))->toBeTrue();
    expect(domainForeignKeys('commission_payouts'))->toMatchArray([
        'user_id' => 'users',
        'paid_by' => 'users',
    ]);
});

it('creates job_logs', function () {
    expect(Schema::hasColumns('job_logs', ['print_job_id', 'user_id', 'from_status', 'to_status', 'note']))->toBeTrue();
    expect(domainForeignKeys('job_logs'))->toMatchArray([
        'print_job_id' => 'print_jobs',
        'user_id' => 'users',
    ]);
});

it('creates key-value settings', function () {
    expect(Schema::hasColumns('settings', ['key', 'value']))->toBeTrue();
    expect(domainUniqueColumns('settings'))->toContain('key');
});

it('creates announcements', function () {
    expect(Schema::hasColumns('announcements', ['title', 'body', 'starts_at', 'ends_at', 'created_by']))->toBeTrue();
    expect(domainForeignKeys('announcements'))->toMatchArray(['created_by' => 'users']);
});

it('creates the default notifications table', function () {
    expect(Schema::hasColumns('notifications', ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at']))->toBeTrue();
});

it('stores money columns as decimals', function (string $table, string $column) {
    $type = collect(Schema::getColumns($table))->firstWhere('name', $column)['type_name'];

    expect(strtolower($type))->toBeIn(['decimal', 'numeric']);
})->with([
    ['services', 'price'],
    ['print_jobs', 'unit_price'],
    ['print_jobs', 'estimated_total'],
    ['print_jobs', 'final_total'],
    ['gcash_payments', 'amount'],
    ['sales', 'amount'],
    ['sales', 'commission_amount'],
    ['walkin_items', 'unit_price'],
    ['walkin_items', 'amount'],
    ['commission_payouts', 'amount'],
]);
