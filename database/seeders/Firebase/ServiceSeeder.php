<?php

namespace Database\Seeders\Firebase;

use App\Repositories\ServiceRepository;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Sample price list with every price at 0; the admin sets real prices.
     */
    public function run(): void
    {
        $repository = app(ServiceRepository::class);

        foreach (['Short', 'Long', 'A4'] as $paperSize) {
            foreach (['bw' => 'Black & White', 'color' => 'Colored'] as $colorMode => $label) {
                $this->firstOrCreate($repository, ['type' => 'document', 'name' => "{$paperSize} {$label}"], [
                    'paper_size' => $paperSize,
                    'color_mode' => $colorMode,
                    'unit' => 'page',
                    'price' => 0,
                    'is_active' => true,
                ]);
            }
        }

        foreach (['1x1' => 8, '2x2' => 4, 'Passport' => 4] as $name => $piecesPerSet) {
            $this->firstOrCreate($repository, ['type' => 'photo', 'name' => $name], [
                'color_mode' => 'color',
                'unit' => 'set',
                'pieces_per_set' => $piecesPerSet,
                'price' => 0,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $attributes
     */
    private function firstOrCreate(ServiceRepository $repository, array $identity, array $attributes): void
    {
        foreach ($repository->all() as $service) {
            if (($service['type'] ?? null) === $identity['type'] && ($service['name'] ?? null) === $identity['name']) {
                return;
            }
        }

        $repository->create(array_merge($identity, $attributes));
    }
}
