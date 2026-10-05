<?php

use App\Auth\RealtimeUser;
use App\Auth\RealtimeUserProvider;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Repositories\UserRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

function realtimeUsers(): array
{
    $repository = app(UserRepository::class);

    $record = $repository->create([
        'student_id' => 'MMC2024-00123',
        'username' => null,
        'name' => 'Ana Cruz',
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
        'password' => Hash::make('secret-password'),
        'role' => 'student',
        'status' => 'approved',
        'must_change_password' => false,
        'approved_at' => now()->toIso8601String(),
        'rejection_reason' => null,
    ]);

    return [$repository, $record];
}

it('resolves users from the realtime database by id and login', function () {
    [, $record] = realtimeUsers();

    $users = app(UserRepository::class);
    $provider = new RealtimeUserProvider($users);

    expect($provider->retrieveById($record['id']))->toBeInstanceOf(RealtimeUser::class)
        ->and($provider->retrieveById($record['id'])->name)->toBe('Ana Cruz')
        ->and($provider->retrieveByCredentials(['login' => 'MMC2024-00123']))->not->toBeNull()
        ->and($provider->retrieveByCredentials(['login' => 'mmc2024-00123']))->not->toBeNull()
        ->and($provider->retrieveByCredentials(['login' => 'nobody']))->toBeNull()
        ->and($provider->retrieveById(999))->toBeNull();
});

it('validates credentials against the stored hash', function () {
    [, $record] = realtimeUsers();

    $provider = new RealtimeUserProvider(app(UserRepository::class));
    $user = $provider->retrieveById($record['id']);

    expect($provider->validateCredentials($user, ['password' => 'secret-password']))->toBeTrue()
        ->and($provider->validateCredentials($user, ['password' => 'wrong']))->toBeFalse()
        ->and($provider->validateCredentials($user, []))->toBeFalse();
});

it('updates and checks remember tokens', function () {
    [, $record] = realtimeUsers();

    $repository = app(UserRepository::class);
    $provider = new RealtimeUserProvider($repository);
    $user = $provider->retrieveById($record['id']);

    $provider->updateRememberToken($user, 'token-123');

    expect($provider->retrieveByToken($record['id'], 'token-123'))->not->toBeNull()
        ->and($provider->retrieveByToken($record['id'], 'other'))->toBeNull();
});

it('casts role, status, boolean and date attributes', function () {
    [, $record] = realtimeUsers();

    $user = new RealtimeUser($record);

    expect($user->role)->toBe(Role::Student)
        ->and($user->status)->toBe(UserStatus::Approved)
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->approved_at)->toBeInstanceOf(Carbon::class)
        ->and($user->getAuthIdentifier())->toBe($record['id'])
        ->and($user->getAuthPassword())->toBe($record['password']);
});

it('registers the rtdb user provider driver', function () {
    expect(app('auth')->createUserProvider('rtdb'))->toBeInstanceOf(RealtimeUserProvider::class);
});
