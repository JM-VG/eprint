<?php

namespace App\Http\Requests\Student;

use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Service;
use App\Rules\RecordExists;
use App\Services\PriceCalculator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('job'));
    }

    /**
     * The file cannot be replaced; options follow the job's type (document or photo).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PrintJob $job */
        $job = $this->route('job');
        $type = $job->service->type;

        $rules = [
            'location_id' => ['required', 'integer', RecordExists::of(Location::class, 'id')],
            'service_id' => [
                'required', 'integer',
                RecordExists::of(Service::class, 'id')->where('type', $type)->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($type === 'photo') {
            return $rules + ['sets' => ['required', 'integer', 'min:1', 'max:100']];
        }

        return $rules + [
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
            'page_range' => ['nullable', 'string', 'max:'.PriceCalculator::MAX_RANGE_LENGTH, function (string $attribute, mixed $value, Closure $fail) {
                if (! PriceCalculator::isValidRange((string) $value)) {
                    $fail('Use a page range like 1-3, 5.');
                }
            }],
            'duplex' => ['boolean'],
        ];
    }
}
