<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every seeder here is safe to run twice.
     */
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            AdminSeeder::class,
            ServiceSeeder::class,
            SettingSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
