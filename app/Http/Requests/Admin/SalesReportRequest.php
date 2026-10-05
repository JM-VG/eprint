<?php

namespace App\Http\Requests\Admin;

use App\Enums\SaleSource;
use App\Models\Location;
use App\Models\User;
use App\Rules\RecordExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends FormRequest
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
            'timeframe' => ['nullable', Rule::in(['today', 'week', 'month', 'custom'])],
            'from' => ['nullable', 'required_if:timeframe,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:timeframe,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer', RecordExists::of(User::class, 'id')],
            'location_id' => ['nullable', 'integer', RecordExists::of(Location::class, 'id')],
            'source' => ['nullable', Rule::enum(SaleSource::class)],
        ];
    }
}
