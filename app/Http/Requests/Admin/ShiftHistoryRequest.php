<?php

namespace App\Http\Requests\Admin;

use App\Models\Location;
use App\Models\User;
use App\Rules\RecordExists;
use Illuminate\Foundation\Http\FormRequest;

class ShiftHistoryRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', RecordExists::of(User::class, 'id')],
            'location_id' => ['nullable', 'integer', RecordExists::of(Location::class, 'id')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
