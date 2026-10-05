<?php

namespace App\Rules;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * No other record in a Realtime Database table may have the value, e.g.
 * RecordUnique::of(User::class, 'username')->ignore($user).
 */
class RecordUnique extends RecordRule
{
    private mixed $ignore = null;

    private string $ignoreColumn = 'id';

    /**
     * Allow the value on this record (a model or its id), as when editing it.
     */
    public function ignore(mixed $record, string $column = 'id'): static
    {
        $this->ignore = $record instanceof Model ? $record->getKey() : $record;
        $this->ignoreColumn = $record instanceof Model ? $record->getKeyName() : $column;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($this->matching($value) as $record) {
            if ($this->ignore !== null && (string) ($record[$this->ignoreColumn] ?? '') === (string) $this->ignore) {
                continue;
            }

            $fail($this->message($attribute, 'unique'));

            return;
        }
    }
}
