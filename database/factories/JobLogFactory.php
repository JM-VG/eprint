<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\JobLog;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobLog>
 */
class JobLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'print_job_id' => PrintJob::factory(),
            'user_id' => User::factory()->staff(),
            'from_status' => JobStatus::Pending,
            'to_status' => JobStatus::Accepted,
            'note' => null,
        ];
    }
}
