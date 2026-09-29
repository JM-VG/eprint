<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function uniqueColumns(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->filter(fn (array $index) => $index['unique'] && count($index['columns']) === 1)
        ->pluck('columns.0')
        ->all();
}

function foreignKeyMap(string $table): array
{
    return collect(Schema::getForeignKeys($table))
        ->mapWithKeys(fn (array $fk) => [$fk['columns'][0] => $fk['foreign_table']])
        ->all();
}

it('adds the spec columns to users', function () {
    expect(Schema::hasColumns('users', [
        'student_id', 'username', 'first_name', 'last_name', 'role', 'status',
        'must_change_password', 'approved_by', 'approved_at', 'rejection_reason',
    ]))->toBeTrue();

    expect(uniqueColumns('users'))->toContain('student_id', 'username');
    expect(foreignKeyMap('users'))->toMatchArray(['approved_by' => 'users']);
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

it('creates the locations table', function () {
    expect(Schema::hasColumns('locations', ['id', 'name', 'slug', 'created_at', 'updated_at']))->toBeTrue();
    expect(uniqueColumns('locations'))->toContain('slug');
});

it('creates the station_sessions table with foreign keys', function () {
    expect(Schema::hasColumns('station_sessions', [
        'id', 'user_id', 'location_id', 'opened_at', 'last_seen_at', 'closed_at', 'close_reason',
    ]))->toBeTrue();

    expect(foreignKeyMap('station_sessions'))->toMatchArray([
        'user_id' => 'users',
        'location_id' => 'locations',
    ]);
});
