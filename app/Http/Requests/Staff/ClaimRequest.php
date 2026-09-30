<?php

namespace App\Http\Requests\Staff;

use App\Enums\PaymentMethod;
use App\Models\GcashPayment;
use App\Models\PrintJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'reference_no' => filled($this->input('reference_no')) ? trim((string) $this->input('reference_no')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', Rule::exists(PrintJob::class, 'claim_code')],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => [
                'nullable',
                'required_if:payment_method,'.PaymentMethod::Gcash->value,
                'string',
                'max:50',
                Rule::unique(GcashPayment::class, 'reference_no'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.exists' => 'No job found with this claim code.',
            'reference_no.required_if' => 'Enter the GCash reference number.',
            'reference_no.unique' => 'This GCash reference number was already used.',
        ];
    }
}
