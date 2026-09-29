<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Sample price list with every price at 0; the admin sets real prices.
     */
    public function run(): void
    {
        foreach (['Short', 'Long', 'A4'] as $paperSize) {
            foreach (['bw' => 'Black & White', 'color' => 'Colored'] as $colorMode => $label) {
                Service::firstOrCreate(
                    ['type' => 'document', 'name' => "{$paperSize} {$label}"],
                    ['paper_size' => $paperSize, 'color_mode' => $colorMode, 'unit' => 'page', 'price' => 0],
                );
            }
        }

        foreach (['1x1' => 8, '2x2' => 4, 'Passport' => 4] as $name => $piecesPerSet) {
            Service::firstOrCreate(
                ['type' => 'photo', 'name' => $name],
                ['color_mode' => 'color', 'unit' => 'set', 'pieces_per_set' => $piecesPerSet, 'price' => 0],
            );
        }
    }
}
