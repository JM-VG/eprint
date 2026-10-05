<?php

namespace App\Http\Requests\Admin;

use App\Enums\JobStatus;
use App\Models\User;
use App\Rules\RecordExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:10'],
            'user_id' => ['nullable', 'integer', RecordExists::of(User::class, 'id')],
            'status' => ['nullable', Rule::enum(JobStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
