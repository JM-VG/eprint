<?php

namespace App\Http\Requests\Staff;

use App\Enums\PaymentMethod;
use App\Models\GcashPayment;
use App\Models\Service;
use App\Rules\RecordExists;
use App\Rules\RecordUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalkinSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_no' => filled($this->input('reference_no')) ? trim((string) $this->input('reference_no')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.service_id' => ['required', 'integer', RecordExists::of(Service::class, 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.sheets_used' => ['required', 'integer', 'min:0', 'max:100000'],
            'items.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => [
                'nullable',
                'required_if:payment_method,'.PaymentMethod::Gcash->value,
                'string',
                'max:50',
                RecordUnique::of(GcashPayment::class, 'reference_no'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item.',
            'reference_no.required_if' => 'Enter the GCash reference number.',
            'reference_no.unique' => 'This GCash reference number was already used.',
        ];
    }
}
