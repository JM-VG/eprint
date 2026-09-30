<?php

namespace App\Http\Requests\Student;

use App\Models\Location;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhotoJobRequest extends FormRequest
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
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')],
            'service_id' => [
                'required', 'integer',
                Rule::exists(Service::class, 'id')->where('type', 'photo')->where('is_active', true),
            ],
            'file' => ['required', 'file', 'max:'.StoreDocumentJobRequest::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png'],
            'sets' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a JPG or PNG photo.',
            'file.max' => 'The photo may not be larger than 20 MB.',
            'service_id.exists' => 'Pick a photo size from the price list.',
        ];
    }
}
