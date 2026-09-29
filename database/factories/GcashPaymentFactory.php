<?php

namespace Database\Factories;

use App\Models\GcashPayment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GcashPayment>
 */
class GcashPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_no' => fake()->unique()->numerify('#############'),
            'amount' => fake()->randomFloat(2, 5, 500),
            'print_job_id' => null,
            'sale_id' => Sale::factory(),
            'received_by' => User::factory()->staff(),
        ];
    }
}
