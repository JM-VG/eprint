<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SaleSource;
use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 5, 500);
        $rate = 10;

        return [
            'user_id' => User::factory()->staff(),
            'print_job_id' => null,
            'location_id' => Location::factory(),
            'source' => SaleSource::Online,
            'payment_method' => PaymentMethod::Cash,
            'amount' => $amount,
            'commission_rate' => $rate,
            'commission_amount' => round($amount * $rate / 100, 2),
            'payout_id' => null,
        ];
    }

    public function walkin(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => SaleSource::Walkin,
            'print_job_id' => null,
        ]);
    }
}
