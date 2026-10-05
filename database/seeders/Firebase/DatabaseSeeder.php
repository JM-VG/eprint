<?php

namespace Database\Seeders\Firebase;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the Firebase Realtime Database. Every seeder here is safe to run twice.
     */
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            AdminSeeder::class,
            ServiceSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
