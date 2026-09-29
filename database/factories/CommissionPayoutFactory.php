<?php

namespace Database\Factories;

use App\Models\CommissionPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionPayout>
 */
class CommissionPayoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->staff(),
            'paid_by' => User::factory()->admin(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'notes' => null,
            'paid_at' => now(),
        ];
    }
}
