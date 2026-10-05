<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewSignup;
use Illuminate\Support\Facades\Notification;

class StudentRegistrationService
{
    public const DEFAULT_PATTERN = '^[A-Z]{3}\d{4}-\d{5}$';

    /**
     * The student ID pattern from settings as a PCRE regex, falling back to the
     * default when the stored pattern is not a valid regular expression.
     */
    public function studentIdRegex(): string
    {
        $regex = $this->toRegex(Setting::read('student_id_pattern', self::DEFAULT_PATTERN));

        return @preg_match($regex, '') === false ? $this->toRegex(self::DEFAULT_PATTERN) : $regex;
    }

    /**
     * Create a pending student account and notify every approved admin.
     *
     * @param  array{student_id: string, first_name: string, last_name: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $student = User::create([
            'student_id' => $data['student_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'password' => $data['password'],
            'role' => Role::Student,
            'status' => UserStatus::Pending,
        ]);

        $admins = User::where('role', Role::Admin)->where('status', UserStatus::Approved)->get();
        Notification::send($admins, new NewSignup($student));

        return $student;
    }

    private function toRegex(string $pattern): string
    {
        return '#'.str_replace('#', '\#', $pattern).'#';
    }
}
