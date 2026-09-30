<?php

namespace App\Http\Requests\Staff;

use App\Services\PriceCalculator;
use Illuminate\Foundation\Http\FormRequest;

class AcceptJobRequest extends FormRequest
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
            'page_count' => ['required', 'integer', 'min:1', 'max:'.PriceCalculator::MAX_PAGES],
            'final_total' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }
}
