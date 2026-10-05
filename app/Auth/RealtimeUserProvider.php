<?php

namespace App\Auth;

use App\Realtime\Database\TableSchema;
use App\Realtime\Database\Values;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

/**
 * Loads users from the Firebase Realtime Database through UserRepository.
 *
 * Registered as the "rtdb" auth user provider (the default; AUTH_PROVIDER).
 * It returns {@see RealtimeUser} models, which are App\Models\User.
 */
class RealtimeUserProvider implements UserProvider
{
    public function __construct(protected UserRepository $users) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        if (! is_scalar($identifier) || $identifier === '') {
            return null;
        }

        return $this->user($this->users->find($identifier));
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = $this->retrieveById($identifier);

        if ($user === null) {
            return null;
        }

        $remembered = (string) $user->getRememberToken();

        return ($remembered !== '' && hash_equals($remembered, (string) $token)) ? $user : null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->users->patch($user->getAuthIdentifier(), ['remember_token' => $token]);
        $user->setRememberToken($token);
    }

    /**
     * Students sign in with their student ID (any case), staff with a username;
     * other lookups (e.g. by email for password resets) match every given column.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $credentials = array_filter(
            $credentials,
            fn ($value, $key) => ! str_contains((string) $key, 'password'),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($credentials === []) {
            return null;
        }

        $login = $credentials['login'] ?? $credentials['student_id'] ?? $credentials['username'] ?? null;

        if ($login !== null) {
            return blank($login) ? null : $this->user($this->users->findByLogin((string) $login));
        }

        foreach ($this->users->all() as $record) {
            if ($this->matches($record, $credentials)) {
                return $this->user($record);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $password = (string) ($credentials['password'] ?? '');

        return $password !== '' && Hash::check($password, $user->getAuthPassword());
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        $password = (string) ($credentials['password'] ?? '');

        if ($password === '') {
            return;
        }

        if ($force || Hash::needsRehash($user->getAuthPassword())) {
            $this->users->patch($user->getAuthIdentifier(), ['password' => Hash::make($password)]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $record
     */
    private function user(?array $record): ?RealtimeUser
    {
        if ($record === null) {
            return null;
        }

        $columns = array_fill_keys(array_keys(app(TableSchema::class)->columns('users')), null);

        return new RealtimeUser(array_merge($columns, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $credentials
     */
    private function matches(array $record, array $credentials): bool
    {
        foreach ($credentials as $column => $value) {
            $actual = $record[$column] ?? null;
            $values = is_array($value) ? $value : [$value];

            if (! collect($values)->contains(fn ($expected) => $expected === null ? $actual === null : Values::equals($actual, $expected))) {
                return false;
            }
        }

        return true;
    }
}
