<?php

namespace App\Realtime\Database;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * Converting values for storage and comparing stored values the way the
 * relational database used to: numbers numerically, dates chronologically,
 * NULL smallest and never equal to anything.
 */
class Values
{
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/';

    /**
     * Convert a value for storage in a column of the given type (null when
     * the column is unknown).
     */
    public static function store(mixed $value, ?string $type = null): mixed
    {
        $value = self::scalar($value);

        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'bool' => is_string($value) ? ! in_array(strtolower($value), ['', '0', 'false', 'off', 'no'], true) : (bool) $value,
            'decimal' => is_numeric($value) ? number_format((float) $value, 2, '.', '') : $value,
            'datetime', 'date' => is_string($value) && self::isDate($value) ? self::formatDate($value) : $value,
            'string' => is_bool($value) ? ($value ? '1' : '0') : (is_int($value) || is_float($value) ? (string) $value : $value),
            default => $value,
        };
    }

    /**
     * Reduce objects to the scalars they are stored as.
     */
    public static function scalar(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Expression => throw UnsupportedQuery::because('raw SQL expressions cannot be stored'),
            $value instanceof DateTimeInterface => Carbon::instance($value)->format(QueryGrammar::DATE_FORMAT),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Stringable => (string) $value,
            default => $value,
        };
    }

    /**
     * Three-way comparison; null when either side is NULL.
     */
    public static function compare(mixed $left, mixed $right): ?int
    {
        $left = self::comparable($left);
        $right = self::comparable($right);

        if ($left === null || $right === null) {
            return null;
        }

        if ($left instanceof Carbon || $right instanceof Carbon) {
            $left = self::toDate($left);
            $right = self::toDate($right);

            if ($left !== null && $right !== null) {
                return $left->getTimestamp() <=> $right->getTimestamp();
            }

            return null;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcmp((string) $left, (string) $right) <=> 0;
    }

    /**
     * Ordering comparison where NULL sorts first, as in SQL.
     */
    public static function sortCompare(mixed $left, mixed $right): int
    {
        $leftNull = self::comparable($left) === null;
        $rightNull = self::comparable($right) === null;

        if ($leftNull || $rightNull) {
            return $rightNull <=> $leftNull;
        }

        return self::compare($left, $right) ?? 0;
    }

    public static function equals(mixed $left, mixed $right): bool
    {
        return self::compare($left, $right) === 0;
    }

    /**
     * Evaluate "column operator value" for a stored value.
     */
    public static function test(mixed $actual, string $operator, mixed $expected): bool
    {
        $operator = strtolower(trim($operator));

        if (in_array($operator, ['like', 'not like', 'ilike', 'not ilike'], true)) {
            $matches = self::like($actual, $expected, false);

            return str_starts_with($operator, 'not') ? ($actual !== null && ! $matches) : $matches;
        }

        if ($operator === '<=>') {
            return (self::comparable($actual) === null && self::comparable($expected) === null)
                || self::equals($actual, $expected);
        }

        $result = self::compare($actual, $expected);

        if ($result === null) {
            return false;
        }

        return match ($operator) {
            '=', '==' => $result === 0,
            '!=', '<>' => $result !== 0,
            '<' => $result < 0,
            '<=' => $result <= 0,
            '>' => $result > 0,
            '>=' => $result >= 0,
            default => throw UnsupportedQuery::because("the [{$operator}] operator is not supported"),
        };
    }

    public static function like(mixed $actual, mixed $pattern, bool $caseSensitive): bool
    {
        if ($actual === null || $pattern === null) {
            return false;
        }

        $regex = '';

        foreach (preg_split('//u', (string) self::scalar($pattern), -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $regex .= match ($char) {
                '%' => '.*',
                '_' => '.',
                default => preg_quote($char, '/'),
            };
        }

        return preg_match('/^'.$regex.'$/s'.($caseSensitive ? '' : 'i').'u', (string) self::scalar($actual)) === 1;
    }

    public static function isDate(string $value): bool
    {
        return preg_match(self::DATE_PATTERN, $value) === 1;
    }

    public static function toDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && self::isDate($value)) {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function formatDate(string $value): string
    {
        $date = self::toDate($value);

        return $date ? $date->format(QueryGrammar::DATE_FORMAT) : $value;
    }

    private static function comparable(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        $value = self::scalar($value);

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_string($value) && ! is_numeric($value) && self::isDate($value)) {
            return self::toDate($value) ?? $value;
        }

        return $value;
    }
}
