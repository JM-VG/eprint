<?php

namespace Database\Seeders\Firebase;

use App\Repositories\SettingRepository;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Default settings. Existing values are kept so admin edits survive re-seeding.
     */
    public function run(): void
    {
        app(SettingRepository::class)->firstOrCreateMany([
            'commission_rate' => '0',
            'gcash_name' => '',
            'gcash_number' => '',
            'gcash_qr_path' => null,
            'retention_days' => '7',
            'student_id_pattern' => '^[A-Z]{3}\d{4}-\d{5}$',
        ]);
    }
}
