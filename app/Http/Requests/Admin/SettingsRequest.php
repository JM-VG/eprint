<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commission_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'gcash_name' => ['nullable', 'string', 'max:100'],
            'gcash_number' => ['nullable', 'regex:/^09\d{9}$/'],
            'gcash_qr' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'student_id_pattern' => ['required', 'string', 'max:255', $this->validRegex(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gcash_number.regex' => 'Enter an 11-digit mobile number starting with 09.',
        ];
    }

    private function validRegex(string $attribute, mixed $value, Closure $fail): void
    {
        if (@preg_match('#'.str_replace('#', '\#', (string) $value).'#', '') === false) {
            $fail('The student ID pattern is not a valid regular expression.');
        }
    }
}
