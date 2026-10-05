<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\RecordUnique;
use App\Services\StudentRegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StudentRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('student_id'))) {
            $this->merge(['student_id' => strtoupper(trim($this->input('student_id')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'string',
                'max:50',
                'regex:'.app(StudentRegistrationService::class)->studentIdRegex(),
                RecordUnique::of(User::class, 'student_id'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.regex' => 'This Student ID does not match the required format.',
            'student_id.unique' => 'This Student ID is already registered. If this is your ID, visit the printing room.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'Student ID',
            'first_name' => 'first name',
            'last_name' => 'last name',
        ];
    }
}
