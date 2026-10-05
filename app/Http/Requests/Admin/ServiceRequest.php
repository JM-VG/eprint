<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use App\Rules\RecordUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
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
            'type' => ['required', Rule::in(['document', 'photo'])],
            'name' => [
                'required', 'string', 'max:100',
                RecordUnique::of(Service::class, 'name')
                    ->where('type', $this->input('type'))
                    ->ignore($this->route('service')),
            ],
            'paper_size' => ['nullable', 'required_if:type,document', 'string', 'max:50'],
            'color_mode' => ['required', Rule::in(['bw', 'color'])],
            'pieces_per_set' => ['nullable', 'required_if:type,photo', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
        ];
    }
}
