<?php

namespace Database\Factories;

use App\Enums\CloseReason;
use App\Models\Location;
use App\Models\StationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StationSession>
 */
class StationSessionFactory extends Factory
{
    /**
     * An open session at a location, seen just now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->staff(),
            'location_id' => Location::factory(),
            'opened_at' => now()->subHour(),
            'last_seen_at' => now(),
            'closed_at' => null,
            'close_reason' => null,
        ];
    }

    public function closed(CloseReason $reason = CloseReason::Manual): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
            'close_reason' => $reason,
        ]);
    }
}
