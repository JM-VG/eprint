<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Service;
use App\Models\WalkinItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalkinItem>
 */
class WalkinItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 20);
        $unitPrice = fake()->randomFloat(2, 1, 10);

        return [
            'sale_id' => Sale::factory()->walkin(),
            'service_id' => Service::factory(),
            'quantity' => $quantity,
            'sheets_used' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => round($quantity * $unitPrice, 2),
        ];
    }
}
