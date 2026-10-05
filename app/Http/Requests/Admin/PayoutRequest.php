<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Rules\RecordExists;
use Illuminate\Foundation\Http\FormRequest;

class PayoutRequest extends FormRequest
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
            'user_id' => [
                'required', 'integer',
                RecordExists::of(User::class, 'id')->whereIn('role', [Role::Staff->value, Role::Admin->value]),
            ],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['user_id.exists' => 'Pick a staff member.'];
    }
}
