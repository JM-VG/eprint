<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Str;

class StaffAccountService
{
    /**
     * Create a staff or admin account with a temporary password.
     *
     * @param  array{username: string, first_name: string, last_name: string, role: string}  $data
     * @return array{0: User, 1: string} the account and its temporary password
     */
    public function create(array $data, User $admin): array
    {
        $password = $this->temporaryPassword();

        $user = new User;
        $user->forceFill([
            'username' => $data['username'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'role' => Role::from($data['role']),
            'status' => UserStatus::Approved,
            'password' => $password,
            'must_change_password' => true,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ])->save();

        return [$user, $password];
    }

    /**
     * @param  array{username: string, first_name: string, last_name: string, role: string}  $data
     */
    public function update(User $user, array $data): void
    {
        $user->forceFill([
            'username' => $data['username'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'role' => Role::from($data['role']),
        ])->save();
    }

    /**
     * Replace any account's password with a temporary one that must be changed on next login.
     */
    public function resetPassword(User $user): string
    {
        $password = $this->temporaryPassword();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        return $password;
    }

    private function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }
}
