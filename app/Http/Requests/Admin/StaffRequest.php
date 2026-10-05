<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Rules\RecordUnique;
use App\Services\StudentRegistrationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === Role::Admin;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => trim($this->input('username'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                RecordUnique::of(User::class, 'username')->ignore($target),
                $this->notAStudentId(...),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'role' => [
                'required',
                Rule::in([Role::Staff->value, Role::Admin->value]),
                function (string $attribute, mixed $value, Closure $fail) use ($target) {
                    if ($target && $target->is($this->user()) && $value !== $target->role->value) {
                        $fail('You cannot change your own role.');
                    }
                },
            ],
        ];
    }

    /**
     * Login tries student IDs first, so a username that equals or looks like a
     * student ID could never log in.
     */
    private function notAStudentId(string $attribute, mixed $value, Closure $fail): void
    {
        $upper = strtoupper((string) $value);

        if (User::where('student_id', $upper)->exists()
            || preg_match(app(StudentRegistrationService::class)->studentIdRegex(), $upper) === 1) {
            $fail('The username cannot be a Student ID.');
        }
    }
}
