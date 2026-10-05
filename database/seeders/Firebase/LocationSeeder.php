<?php

namespace Database\Seeders\Firebase;

use App\Repositories\LocationRepository;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            'main-it' => 'Main IT Printing Room',
            'ccs-office' => 'CCS Office',
        ];

        $repository = app(LocationRepository::class);

        foreach ($locations as $slug => $name) {
            if ($repository->findBySlug($slug) === null) {
                $repository->create(['name' => $name, 'slug' => $slug]);
            }
        }
    }
}
