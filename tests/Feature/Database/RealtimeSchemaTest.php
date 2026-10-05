<?php

use App\Firebase\DatabaseClient;
use App\Realtime\Database\TableSchema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
| The Realtime Database has no schema of its own. TableSchema keeps what the
| relational migrations used to enforce; these tests replace the old
| migration tests and check both the declarations and their effect.
*/

function schemaTable(): TableSchema
{
    return app(TableSchema::class);
}

function schemaForeignKeys(string $table): array
{
    return collect(schemaTable()->foreignKeys($table))
        ->map(fn (array $reference) => $reference[0])
        ->all();
}

it('declares the spec columns for users', function () {
    expect(schemaTable()->columns('users'))->toHaveKeys([
        'student_id', 'username', 'first_name', 'last_name', 'role', 'status',
        'must_change_password', 'approved_by', 'approved_at', 'rejection_reason',
    ]);

    expect(schemaTable()->unique('users'))->toContain('student_id', 'username');
    expect(schemaForeignKeys('users'))->toMatchArray(['approved_by' => 'users']);
});

it('lets student_id and username be null for multiple users', function () {
    DB::table('users')->insert([
        ['name' => 'A', 'password' => 'x', 'role' => 'student', 'status' => 'pending'],
        ['name' => 'B', 'password' => 'x', 'role' => 'student', 'status' => 'pending'],
    ]);

    expect(DB::table('users')->whereNull('student_id')->whereNull('username')->count())->toBe(2);
});

it('defaults new users to a pending student with no forced password change', function () {
    $id = DB::table('users')->insertGetId(['name' => 'A', 'password' => 'x']);
    $user = DB::table('users')->find($id);

    expect($user->role)->toBe('student')
        ->and($user->status)->toBe('pending')
        ->and((bool) $user->must_change_password)->toBeFalse()
        ->and($user->email)->toBeNull();
});

it('declares the locations table with a unique slug', function () {
    expect(schemaTable()->columns('locations'))->toHaveKeys(['id', 'name', 'slug', 'created_at', 'updated_at']);
    expect(schemaTable()->unique('locations'))->toContain('slug');
});

it('declares station_sessions with foreign keys', function () {
    expect(schemaTable()->columns('station_sessions'))->toHaveKeys([
        'id', 'user_id', 'location_id', 'opened_at', 'last_seen_at', 'closed_at', 'close_reason',
    ]);

    expect(schemaForeignKeys('station_sessions'))->toMatchArray([
        'user_id' => 'users',
        'location_id' => 'locations',
    ]);
});

it('declares the services price list', function () {
    expect(schemaTable()->columns('services'))->toHaveKeys([
        'type', 'name', 'paper_size', 'color_mode', 'unit', 'pieces_per_set', 'price', 'is_active',
    ]);
});

it('declares print_jobs with spec columns and foreign keys', function () {
    expect(schemaTable()->columns('print_jobs'))->toHaveKeys([
        'claim_code', 'user_id', 'location_id', 'service_id', 'source', 'original_name', 'stored_path',
        'pdf_path', 'file_type', 'file_size', 'page_count', 'page_range', 'copies', 'duplex', 'sets',
        'notes', 'unit_price', 'estimated_total', 'final_total', 'sheets_used', 'status',
        'payment_method', 'payment_status', 'reject_reason', 'received_by', 'claimed_at', 'files_deleted_at',
    ]);

    expect(schemaTable()->unique('print_jobs'))->toContain('claim_code');
    expect(schemaForeignKeys('print_jobs'))->toMatchArray([
        'user_id' => 'users',
        'location_id' => 'locations',
        'service_id' => 'services',
        'received_by' => 'users',
    ]);
});

it('declares gcash_payments with a unique reference number', function () {
    expect(schemaTable()->columns('gcash_payments'))->toHaveKeys(['reference_no', 'amount', 'print_job_id', 'sale_id', 'received_by']);
    expect(schemaTable()->unique('gcash_payments'))->toContain('reference_no');
    expect(schemaForeignKeys('gcash_payments'))->toMatchArray([
        'print_job_id' => 'print_jobs',
        'sale_id' => 'sales',
        'received_by' => 'users',
    ]);
});

it('declares sales with commission snapshot and payout link', function () {
    expect(schemaTable()->columns('sales'))->toHaveKeys([
        'user_id', 'print_job_id', 'location_id', 'source', 'payment_method', 'amount',
        'commission_rate', 'commission_amount', 'payout_id',
    ]);
    expect(schemaForeignKeys('sales'))->toMatchArray([
        'user_id' => 'users',
        'print_job_id' => 'print_jobs',
        'location_id' => 'locations',
        'payout_id' => 'commission_payouts',
    ]);
});

it('declares walkin_items linked to sales and services', function () {
    expect(schemaTable()->columns('walkin_items'))->toHaveKeys(['sale_id', 'service_id', 'quantity', 'sheets_used', 'unit_price', 'amount']);
    expect(schemaForeignKeys('walkin_items'))->toMatchArray([
        'sale_id' => 'sales',
        'service_id' => 'services',
    ]);
});

it('declares commission_payouts', function () {
    expect(schemaTable()->columns('commission_payouts'))->toHaveKeys(['user_id', 'paid_by', 'period_start', 'period_end', 'amount', 'paid_at']);
    expect(schemaForeignKeys('commission_payouts'))->toMatchArray([
        'user_id' => 'users',
        'paid_by' => 'users',
    ]);
});

it('declares job_logs', function () {
    expect(schemaTable()->columns('job_logs'))->toHaveKeys(['print_job_id', 'user_id', 'from_status', 'to_status', 'note']);
    expect(schemaForeignKeys('job_logs'))->toMatchArray([
        'print_job_id' => 'print_jobs',
        'user_id' => 'users',
    ]);
});

it('declares key-value settings', function () {
    expect(schemaTable()->columns('settings'))->toHaveKeys(['key', 'value']);
    expect(schemaTable()->unique('settings'))->toContain('key');
});

it('declares announcements', function () {
    expect(schemaTable()->columns('announcements'))->toHaveKeys(['title', 'body', 'starts_at', 'ends_at', 'created_by']);
    expect(schemaForeignKeys('announcements'))->toMatchArray(['created_by' => 'users']);
});

it('declares the default notifications table', function () {
    expect(schemaTable()->columns('notifications'))->toHaveKeys(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at']);
});

it('rejects duplicate values in unique columns', function (string $table, array $row) {
    DB::table($table)->insert($row);

    expect(fn () => DB::table($table)->insert($row))->toThrow(UniqueConstraintViolationException::class);
})->with([
    'student id' => ['users', ['name' => 'A', 'password' => 'x', 'student_id' => 'MMC2024-00001']],
    'username' => ['users', ['name' => 'A', 'password' => 'x', 'username' => 'staff1']],
    'location slug' => ['locations', ['name' => 'Main', 'slug' => 'main']],
    'claim code' => ['print_jobs', ['claim_code' => 'EP-AAAA', 'user_id' => 1, 'location_id' => 1, 'service_id' => 1, 'original_name' => 'a.pdf', 'file_type' => 'pdf', 'file_size' => 1, 'unit_price' => 1]],
    'gcash reference' => ['gcash_payments', ['reference_no' => 'REF-1', 'amount' => 1]],
]);

it('stores money columns as two-decimal strings', function (string $table, string $column) {
    expect(schemaTable()->type($table, $column))->toBe('decimal');

    $id = DB::table($table)->insertGetId([$column => 12.5]);

    expect(app(DatabaseClient::class)->get("{$table}/{$id}/{$column}"))->toBe('12.50');
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
