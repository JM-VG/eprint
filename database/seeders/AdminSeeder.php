<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Create the super admin from ADMIN_USERNAME / ADMIN_PASSWORD.
     * An existing admin is left untouched so a changed password survives re-seeding.
     */
    public function run(): void
    {
        $username = config('eprint.admin.username');
        $password = config('eprint.admin.password');

        if (blank($username) || blank($password)) {
            $this->command?->warn('ADMIN_USERNAME or ADMIN_PASSWORD is not set; skipping admin account.');

            return;
        }

        User::firstOrCreate(['username' => $username], [
            'name' => 'System Administrator',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'password' => $password,
            'role' => Role::Admin,
            'status' => UserStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
