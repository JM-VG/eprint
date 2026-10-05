<?php

namespace App\Http\Requests\Student;

use App\Models\Location;
use App\Models\Service;
use App\Rules\RecordExists;
use App\Services\PriceCalculator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentJobRequest extends FormRequest
{
    public const MAX_UPLOAD_KB = 20480;

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
            'service_id' => [
                'required', 'integer',
                RecordExists::of(Service::class, 'id')->where('type', 'document')->where('is_active', true),
            ],
            'file' => ['required', 'file', 'max:'.self::MAX_UPLOAD_KB, 'mimes:pdf,docx,jpg,jpeg,png'],
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
            'page_range' => ['nullable', 'string', 'max:'.PriceCalculator::MAX_RANGE_LENGTH, $this->validRange(...)],
            'duplex' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a PDF, DOCX, JPG or PNG file.',
            'file.max' => 'The file may not be larger than 20 MB.',
            'service_id.exists' => 'Pick a document service from the price list.',
        ];
    }

    private function validRange(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PriceCalculator::isValidRange((string) $value)) {
            $fail('Use a page range like 1-3, 5.');
        }
    }
}
