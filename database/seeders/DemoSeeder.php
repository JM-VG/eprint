<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Demo staff, students and jobs for local development only.
     * Every demo account uses the password "password".
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DemoSeeder only runs in the local environment.');

            return;
        }

        foreach (['staff1' => ['Maria', 'Santos'], 'staff2' => ['Jose', 'Reyes']] as $username => [$first, $last]) {
            User::firstOrCreate(['username' => $username], [
                'name' => "{$first} {$last}",
                'first_name' => $first,
                'last_name' => $last,
                'password' => 'password',
                'role' => Role::Staff,
                'status' => UserStatus::Approved,
            ]);
        }

        $students = [
            'MMC2024-00001' => ['Ana', 'Cruz', UserStatus::Approved],
            'MMC2024-00002' => ['Ben', 'Garcia', UserStatus::Approved],
            'MMC2024-00003' => ['Carla', 'Lopez', UserStatus::Pending],
        ];

        foreach ($students as $studentId => [$first, $last, $status]) {
            User::firstOrCreate(['student_id' => $studentId], [
                'name' => "{$first} {$last}",
                'first_name' => $first,
                'last_name' => $last,
                'password' => 'password',
                'role' => Role::Student,
                'status' => $status,
            ]);
        }

        $student = User::where('student_id', 'MMC2024-00001')->sole();

        if ($student->printJobs()->exists()) {
            return;
        }

        $service = Service::where('type', 'document')->firstOrFail();

        foreach (Location::all() as $location) {
            PrintJob::factory()->count(2)->create([
                'user_id' => $student->id,
                'location_id' => $location->id,
                'service_id' => $service->id,
                'unit_price' => $service->price,
                'estimated_total' => 0,
            ]);
        }
    }
}
