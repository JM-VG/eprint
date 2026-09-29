<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleSource;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PrintJob>
 */
class PrintJobFactory extends Factory
{
    /**
     * A pending online document job.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pages = fake()->numberBetween(1, 30);
        $copies = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomFloat(2, 1, 10);

        return [
            'claim_code' => 'EP-'.fake()->unique()->regexify('[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}'),
            'user_id' => User::factory(),
            'location_id' => Location::factory(),
            'service_id' => Service::factory(),
            'source' => SaleSource::Online,
            'original_name' => fake()->word().'.pdf',
            'stored_path' => 'uploads/'.Str::random(40).'.pdf',
            'pdf_path' => null,
            'file_type' => 'pdf',
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            'page_count' => $pages,
            'page_range' => null,
            'copies' => $copies,
            'duplex' => fake()->boolean(),
            'sets' => null,
            'notes' => null,
            'unit_price' => $unitPrice,
            'estimated_total' => round($pages * $copies * $unitPrice, 2),
            'final_total' => null,
            'sheets_used' => null,
            'status' => JobStatus::Pending,
            'payment_method' => null,
            'payment_status' => PaymentStatus::Unpaid,
        ];
    }

    public function claimed(PaymentMethod $method = PaymentMethod::Cash): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::Claimed,
            'final_total' => $attributes['estimated_total'],
            'sheets_used' => $attributes['page_count'] * $attributes['copies'],
            'payment_method' => $method,
            'payment_status' => PaymentStatus::Paid,
            'received_by' => User::factory()->staff(),
            'claimed_at' => now(),
        ]);
    }
}
