<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * A document service priced per page.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paperSize = fake()->randomElement(['Short', 'Long', 'A4']);
        $colorMode = fake()->randomElement(['bw', 'color']);

        return [
            'type' => 'document',
            'name' => "{$paperSize} ".($colorMode === 'bw' ? 'Black & White' : 'Colored'),
            'paper_size' => $paperSize,
            'color_mode' => $colorMode,
            'unit' => 'page',
            'pieces_per_set' => null,
            'price' => fake()->randomFloat(2, 1, 20),
            'is_active' => true,
        ];
    }

    public function document(): static
    {
        return $this;
    }

    public function photo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'photo',
            'name' => fake()->randomElement(['1x1', '2x2', 'Passport']),
            'paper_size' => null,
            'color_mode' => 'color',
            'unit' => 'set',
            'pieces_per_set' => fake()->randomElement([4, 6, 8]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
