<?php

namespace App\Rules;

use App\Realtime\Database\Values;
use App\Repositories\RepositoryRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Shared base for validation rules that look records up through the
 * Realtime Database repositories (replacing Rule::exists / Rule::unique).
 */
abstract class RecordRule implements ValidationRule, ValidatorAwareRule
{
    protected ?Validator $validator = null;

    protected string $table;

    /**
     * @var list<array{0: string, 1: list<mixed>}> column => accepted values
     */
    protected array $conditions = [];

    /**
     * @param  class-string<Model>|string  $table  a model class or table name
     */
    final public function __construct(string $table, protected string $column = 'id')
    {
        $this->table = class_exists($table) && is_subclass_of($table, Model::class)
            ? (new $table)->getTable()
            : $table;
    }

    /**
     * @param  class-string<Model>|string  $table
     */
    public static function of(string $table, string $column = 'id'): static
    {
        return new static($table, $column);
    }

    /**
     * Only count records whose column equals the value (null matches null).
     */
    public function where(string $column, mixed $value): static
    {
        $this->conditions[] = [$column, [$value]];

        return $this;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public function whereIn(string $column, array $values): static
    {
        $this->conditions[] = [$column, array_values($values)];

        return $this;
    }

    public function setValidator($validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * The message for a failure: a custom "attribute.rule" message from the form
     * request (as with Rule::exists/unique), else the default translation.
     */
    protected function message(string $attribute, string $rule): string
    {
        foreach ($this->validator?->customMessages ?? [] as $key => $message) {
            if ($key === $rule || Str::is($key, "{$attribute}.{$rule}")) {
                return $message;
            }
        }

        return __("validation.{$rule}");
    }

    /**
     * Records whose column equals the value and that satisfy every condition.
     *
     * @return list<array<string, mixed>>
     */
    protected function matching(mixed $value): array
    {
        $matches = [];

        foreach (app(RepositoryRegistry::class)->for($this->table)->all() as $record) {
            if ($this->same($record[$this->column] ?? null, $value) && $this->satisfies($record)) {
                $matches[] = $record;
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function satisfies(array $record): bool
    {
        foreach ($this->conditions as [$column, $values]) {
            $actual = $record[$column] ?? null;

            if (! collect($values)->contains(fn ($expected) => $this->same($actual, $expected))) {
                return false;
            }
        }

        return true;
    }

    private function same(mixed $actual, mixed $expected): bool
    {
        $expected = Values::scalar($expected);

        if ($expected === null || $actual === null) {
            return $expected === null && $actual === null;
        }

        return Values::equals($actual, $expected);
    }
}
