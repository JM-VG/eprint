<?php

namespace Database\Seeders\Firebase;

use App\Repositories\UserRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $repository = app(UserRepository::class);

        if ($repository->findByUsername($username) !== null) {
            return;
        }

        $repository->create([
            'username' => $username,
            'name' => 'System Administrator',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'password' => Hash::make($password),
            'role' => 'admin',
            'status' => 'approved',
            'must_change_password' => false,
            'approved_at' => now()->toIso8601String(),
        ]);
    }
}
