<?php

namespace App\Rules;

use Closure;

/**
 * The value must match a record in a Realtime Database table, e.g.
 * RecordExists::of(Service::class)->where('is_active', true).
 */
class RecordExists extends RecordRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || $this->matching($value) === []) {
            $fail($this->message($attribute, 'exists'));
        }
    }
}
