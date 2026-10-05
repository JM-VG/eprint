<?php

namespace App\Http\Requests\Staff;

use App\Models\Location;
use App\Rules\RecordExists;
use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
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
            'location_id' => ['required', 'integer', RecordExists::of(Location::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['location_id.required' => 'Pick the location you are working at.'];
    }
}
