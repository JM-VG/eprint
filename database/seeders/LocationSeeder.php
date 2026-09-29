<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            'main-it' => 'Main IT Printing Room',
            'ccs-office' => 'CCS Office',
        ];

        foreach ($locations as $slug => $name) {
            Location::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
