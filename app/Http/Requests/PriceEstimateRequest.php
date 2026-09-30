<?php

namespace App\Http\Requests;

use App\Http\Requests\Student\StoreDocumentJobRequest;
use App\Models\Service;
use App\Services\PriceCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceEstimateRequest extends FormRequest
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
            'service_id' => ['required', 'integer', Rule::exists(Service::class, 'id')->where('is_active', true)],
            'file' => ['nullable', 'file', 'max:'.StoreDocumentJobRequest::MAX_UPLOAD_KB, 'mimes:pdf,docx,jpg,jpeg,png'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sets' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page_range' => ['nullable', 'string', 'max:'.PriceCalculator::MAX_RANGE_LENGTH],
        ];
    }
}
