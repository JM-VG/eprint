<?php

namespace App\Auth;

use App\Repositories\UserRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

/**
 * Loads users from the Firebase Realtime Database through UserRepository.
 *
 * Registered as the "rtdb" auth user provider. Enable it with
 * AUTH_PROVIDER=rtdb once the application reads users from Realtime Database.
 */
class RealtimeUserProvider implements UserProvider
{
    public function __construct(protected UserRepository $users) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        $record = $this->users->find($identifier);

        return $record === null ? null : new RealtimeUser($record);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $record = $this->users->find($identifier);

        if ($record === null) {
            return null;
        }

        $remembered = (string) ($record['remember_token'] ?? '');

        return ($remembered !== '' && hash_equals($remembered, (string) $token)) ? new RealtimeUser($record) : null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->users->update($user->getAuthIdentifier(), ['remember_token' => $token]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $login = $credentials['login']
            ?? $credentials['student_id']
            ?? $credentials['username']
            ?? null;

        if (blank($login)) {
            return null;
        }

        $record = $this->users->findByLogin((string) $login);

        return $record === null ? null : new RealtimeUser($record);
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
            $this->users->update($user->getAuthIdentifier(), ['password' => Hash::make($password)]);
        }
    }
}
