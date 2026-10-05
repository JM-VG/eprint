<?php

namespace App\Realtime\Database;

use App\Realtime\Database\Stores\TableStore;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use RuntimeException;

/**
 * Runs Laravel queries against the Realtime Database by evaluating them in
 * PHP over each table's records.
 *
 * Supported: where (basic, in, null, between, nested, column, date parts,
 * like, non-correlated exists), orderBy, limit/offset, distinct, column
 * selection, aggregates (count/sum/avg/min/max), insert/update/delete/upsert
 * and increments, with the column defaults, unique columns and on-delete
 * rules from {@see TableSchema}.
 *
 * Not supported (throws {@see UnsupportedQuery}): joins, unions, GROUP BY,
 * HAVING, raw expressions and correlated subqueries.
 *
 * @property RealtimeConnection $connection
 */
class QueryBuilder extends Builder
{
    /**
     * Key lookups with at most this many values read records one by one
     * instead of loading the whole table.
     */
    private const DIRECT_LOOKUP_LIMIT = 25;

    private const WHERE_TYPES = [
        'Basic', 'NullSafeEquals', 'Like', 'In', 'InRaw', 'NotIn', 'NotInRaw', 'Null', 'NotNull',
        'between', 'Nested', 'Column', 'Date', 'Time', 'Day', 'Month', 'Year', 'Exists', 'NotExists',
    ];

    protected function runSelect()
    {
        $this->guardAgainstSql();

        if ($this->aggregate !== null) {
            return [(object) ['aggregate' => $this->computeAggregate($this->filteredRows())]];
        }

        $rows = $this->sliced($this->sorted($this->filteredRows()));
        $rows = array_map(fn (array $row) => $this->project($row), $rows);

        if ($this->distinct) {
            $rows = array_values(array_unique($rows, SORT_REGULAR));
        }

        return array_map(fn (array $row) => (object) $row, $rows);
    }

    public function exists()
    {
        $this->applyBeforeQueryCallbacks();
        $this->guardAgainstSql();

        return $this->sliced($this->filteredRows()) !== [];
    }

    public function cursor()
    {
        return new LazyCollection(function () {
            yield from $this->get()->all();
        });
    }

    public function insert(array $values)
    {
        if ($values === []) {
            return true;
        }

        $this->applyBeforeQueryCallbacks();
        $rows = array_is_list($values) && is_array(reset($values)) ? $values : [$values];

        $this->connection->atomically(function () use ($rows) {
            foreach ($rows as $row) {
                $this->insertRow($row);
            }
        });

        return true;
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->applyBeforeQueryCallbacks();

        return $this->connection->atomically(fn () => $this->insertRow($values));
    }

    public function insertOrIgnore(array $values)
    {
        if ($values === []) {
            return 0;
        }

        $this->applyBeforeQueryCallbacks();
        $rows = array_is_list($values) && is_array(reset($values)) ? $values : [$values];

        return $this->connection->atomically(function () use ($rows) {
            $inserted = 0;

            foreach ($rows as $row) {
                try {
                    $this->insertRow($row);
                    $inserted++;
                } catch (UniqueConstraintViolationException) {
                    //
                }
            }

            return $inserted;
        });
    }

    public function update(array $values)
    {
        $this->applyBeforeQueryCallbacks();
        $this->guardAgainstSql();
        $values = $this->stripKeys($values);

        return $this->connection->atomically(function () use ($values) {
            $rows = $this->sliced($this->sorted($this->filteredRows()));

            foreach ($rows as $row) {
                $this->updateRow($row, $values);
            }

            return count($rows);
        });
    }

    public function upsert(array $values, array|string $uniqueBy, ?array $update = null)
    {
        if ($values === []) {
            return 0;
        }

        $rows = array_is_list($values) && is_array(reset($values)) ? $values : [$values];
        $uniqueBy = (array) $uniqueBy;

        return $this->connection->atomically(function () use ($rows, $uniqueBy, $update) {
            foreach ($rows as $row) {
                $row = $this->stripKeys($row);
                $existing = null;

                foreach ($this->store()->rows() as $candidate) {
                    if (collect($uniqueBy)->every(fn ($column) => Values::equals($candidate[$column] ?? null, $row[$column] ?? null))) {
                        $existing = $candidate;

                        break;
                    }
                }

                if ($existing === null) {
                    $this->insertRow($row);

                    continue;
                }

                $columns = $update ?? array_keys($row);
                $changes = [];

                foreach ($columns as $key => $column) {
                    if (is_string($key)) {
                        $changes[$key] = $column;
                    } elseif (array_key_exists($column, $row)) {
                        $changes[$column] = $row[$column];
                    }
                }

                $this->updateRow($existing, $changes);
            }

            return count($rows);
        });
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        return $this->adjust($columns, $extra, 1);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        return $this->adjust($columns, $extra, -1);
    }

    public function delete($id = null)
    {
        if ($id !== null) {
            $this->where($this->store()->keyName(), '=', $id);
        }

        $this->applyBeforeQueryCallbacks();
        $this->guardAgainstSql();

        return $this->connection->atomically(function () {
            $rows = $this->sliced($this->sorted($this->filteredRows()));

            foreach ($rows as $row) {
                $this->deleteRow($this->table(), $row[$this->store()->keyName()]);
            }

            return count($rows);
        });
    }

    public function truncate()
    {
        $this->applyBeforeQueryCallbacks();
        $this->connection->atomically(fn () => $this->store()->truncate());
    }

    /**
     * @param  array<string, int|float>  $columns
     * @param  array<string, mixed>  $extra
     */
    private function adjust(array $columns, array $extra, int $sign): int
    {
        foreach ($columns as $column => $amount) {
            if (! is_numeric($amount)) {
                throw new \InvalidArgumentException("Non-numeric value passed as increment amount for column: '$column'.");
            }
        }

        $this->applyBeforeQueryCallbacks();
        $this->guardAgainstSql();

        return $this->connection->atomically(function () use ($columns, $extra, $sign) {
            $rows = $this->sliced($this->sorted($this->filteredRows()));

            foreach ($rows as $row) {
                $values = $this->stripKeys($extra);

                foreach ($this->stripKeys($columns) as $column => $amount) {
                    $values[$column] = ($row[$column] ?? 0) + $sign * $amount;
                }

                $this->updateRow($row, $values);
            }

            return count($rows);
        });
    }

    // ---------------------------------------------------------------- writes

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertRow(array $values): int|string
    {
        $table = $this->table();
        $schema = $this->connection->tableSchema();
        $row = $this->stripKeys($values);

        foreach ($schema->defaults($table) as $column => $default) {
            if (! array_key_exists($column, $row)) {
                $row[$column] = $default;
            }
        }

        $row = $this->normalize($row);
        $this->assertUnique($row, null);

        return $this->store()->insert($row);
    }

    /**
     * @param  array<string, mixed>  $row  the current row
     * @param  array<string, mixed>  $values
     */
    private function updateRow(array $row, array $values): void
    {
        $values = $this->normalize($values);
        $keyName = $this->store()->keyName();
        $key = $row[$keyName];

        $this->assertUnique(array_merge($row, $values), $key);
        $this->store()->patch($key, $values);
    }

    /**
     * Delete a row after applying the on-delete rules of rows that reference it.
     */
    private function deleteRow(string $table, int|string $key): void
    {
        $schema = $this->connection->tableSchema();

        foreach ($schema->referencing($table) as $reference) {
            $children = $this->connection->store($reference['table']);

            foreach ($children->rows() as $childKey => $child) {
                if (! Values::equals($child[$reference['column']] ?? null, $key)) {
                    continue;
                }

                match ($reference['onDelete']) {
                    TableSchema::CASCADE => $this->deleteRow($reference['table'], $childKey),
                    TableSchema::SET_NULL => $children->patch($childKey, [$reference['column'] => null]),
                    default => throw $this->constraintViolation(
                        "FOREIGN KEY constraint failed: {$reference['table']}.{$reference['column']} references {$table}.{$key}",
                    ),
                };
            }
        }

        $this->connection->store($table)->delete($key);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertUnique(array $row, int|string|null $ignoreKey): void
    {
        $columns = array_filter(
            $this->connection->tableSchema()->unique($this->table()),
            fn (string $column) => ($row[$column] ?? null) !== null,
        );

        if ($columns === []) {
            return;
        }

        foreach ($this->store()->rows() as $key => $existing) {
            if ($ignoreKey !== null && (string) $key === (string) $ignoreKey) {
                continue;
            }

            foreach ($columns as $column) {
                if (Values::equals($existing[$column] ?? null, $row[$column])) {
                    throw new UniqueConstraintViolationException(
                        $this->connection->getName(),
                        "insert into {$this->table()}",
                        [],
                        new RuntimeException("UNIQUE constraint failed: {$this->table()}.{$column}"),
                    );
                }
            }
        }
    }

    private function constraintViolation(string $message): QueryException
    {
        return new QueryException($this->connection->getName(), "delete from {$this->table()}", [], new RuntimeException($message));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        $schema = $this->connection->tableSchema();
        $table = $this->table();

        foreach ($values as $column => $value) {
            $values[$column] = Values::store($value, $schema->type($table, $column));
        }

        return $values;
    }

    /**
     * Turn "table.column" keys into "column".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function stripKeys(array $values): array
    {
        $stripped = [];

        foreach ($values as $column => $value) {
            $stripped[$this->column((string) $column)] = $value;
        }

        return $stripped;
    }

    // ----------------------------------------------------------------- reads

    /**
     * Rows matching the where clauses, in key order.
     *
     * @return list<array<string, mixed>>
     */
    private function filteredRows(): array
    {
        $rows = [];

        foreach ($this->candidates() as $row) {
            if ($this->matchesAll($this->wheres, $row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Read only the records a key lookup needs; otherwise the whole table.
     *
     * @return iterable<array<string, mixed>>
     */
    private function candidates(): iterable
    {
        $store = $this->store();
        $keys = $this->directKeys($store);

        if ($keys === null) {
            return $store->rows();
        }

        $rows = [];

        foreach (array_unique(array_map('strval', $keys)) as $key) {
            if (($row = $store->find($key)) !== null) {
                $rows[$key] = $row;
            }
        }

        ksort($rows);

        return $rows;
    }

    /**
     * The keys an AND-only query is restricted to, or null.
     *
     * @return list<int|string>|null
     */
    private function directKeys(TableStore $store): ?array
    {
        foreach ($this->wheres as $where) {
            if (($where['boolean'] ?? 'and') !== 'and') {
                return null;
            }
        }

        foreach ($this->wheres as $where) {
            $column = isset($where['column']) && is_string($where['column']) ? $this->column($where['column']) : null;

            if ($column !== $store->keyName()) {
                continue;
            }

            if ($where['type'] === 'Basic' && in_array($where['operator'], ['=', '=='], true) && is_scalar($where['value'])) {
                return [$where['value']];
            }

            if (in_array($where['type'], ['In', 'InRaw'], true) && count($where['values']) <= self::DIRECT_LOOKUP_LIMIT) {
                $values = array_map(fn ($value) => Values::scalar($value), array_values($where['values']));

                if (collect($values)->every(fn ($value) => is_scalar($value))) {
                    return $values;
                }
            }
        }

        return null;
    }

    /**
     * Where clauses combine like SQL: AND binds tighter than OR.
     *
     * @param  array<int, array<string, mixed>>  $wheres
     * @param  array<string, mixed>  $row
     */
    private function matchesAll(array $wheres, array $row): bool
    {
        if ($wheres === []) {
            return true;
        }

        $group = true;

        foreach (array_values($wheres) as $index => $where) {
            if ($index > 0 && strtolower($where['boolean'] ?? 'and') === 'or') {
                if ($group) {
                    return true;
                }

                $group = true;
            }

            if ($group && ! $this->matches($where, $row)) {
                $group = false;
            }
        }

        return $group;
    }

    /**
     * @param  array<string, mixed>  $where
     * @param  array<string, mixed>  $row
     */
    private function matches(array $where, array $row): bool
    {
        $value = fn (string $column) => $row[$this->column($column)] ?? null;

        return match ($where['type']) {
            'Basic' => Values::test($value($where['column']), $where['operator'], $this->bound($where['value'])),
            'NullSafeEquals' => Values::test($value($where['column']), '<=>', $this->bound($where['value'])),
            'Like' => ($where['not'] ?? false)
                ? $value($where['column']) !== null && ! Values::like($value($where['column']), $where['value'], (bool) $where['caseSensitive'])
                : Values::like($value($where['column']), $where['value'], (bool) $where['caseSensitive']),
            'In', 'InRaw' => $this->inList($value($where['column']), $where['values']),
            'NotIn', 'NotInRaw' => $value($where['column']) !== null && ! $this->inList($value($where['column']), $where['values']),
            'Null' => $value($where['column']) === null,
            'NotNull' => $value($where['column']) !== null,
            'between' => $this->between($value($where['column']), $where['values'], (bool) $where['not']),
            'Nested' => $this->matchesAll($where['query']->wheres, $row),
            'Column' => $this->compareColumns($where, $row),
            'Date', 'Time', 'Day', 'Month', 'Year' => $this->datePart($where, $value($where['column'])),
            'Exists' => $this->subquery($where['query'])->exists(),
            'NotExists' => ! $this->subquery($where['query'])->exists(),
            default => throw UnsupportedQuery::because("where clauses of type [{$where['type']}] are not supported"),
        };
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function inList(mixed $actual, array $values): bool
    {
        foreach ($values as $candidate) {
            if (Values::equals($actual, $this->bound($candidate))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function between(mixed $actual, array $values, bool $not): bool
    {
        [$min, $max] = array_values($values);
        $low = Values::compare($actual, $this->bound($min));
        $high = Values::compare($actual, $this->bound($max));

        if ($low === null || $high === null) {
            return false;
        }

        $inside = $low >= 0 && $high <= 0;

        return $not ? ! $inside : $inside;
    }

    /**
     * @param  array<string, mixed>  $where
     * @param  array<string, mixed>  $row
     */
    private function compareColumns(array $where, array $row): bool
    {
        foreach ([$where['first'], $where['second']] as $column) {
            if (! is_string($column)) {
                throw UnsupportedQuery::because('column comparisons must name columns');
            }

            if (str_contains($column, '.') && ! $this->isOwnColumn($column)) {
                throw UnsupportedQuery::because("correlated subqueries are not supported ({$column})");
            }
        }

        return Values::test($row[$this->column($where['first'])] ?? null, $where['operator'], $row[$this->column($where['second'])] ?? null);
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function datePart(array $where, mixed $actual): bool
    {
        $date = Values::toDate($actual);

        if ($date === null) {
            return false;
        }

        $date = $date->copy()->setTimezone(config('app.timezone'));
        $expected = $this->bound($where['value']);
        $expected = $expected instanceof \DateTimeInterface ? Carbon::instance($expected) : $expected;

        [$left, $right] = match ($where['type']) {
            'Date' => [$date->format('Y-m-d'), $expected instanceof Carbon ? $expected->format('Y-m-d') : (string) $expected],
            'Time' => [$date->format('H:i:s'), $expected instanceof Carbon ? $expected->format('H:i:s') : (string) $expected],
            'Day' => [(int) $date->format('d'), (int) $expected],
            'Month' => [(int) $date->format('m'), (int) $expected],
            'Year' => [(int) $date->format('Y'), (int) $expected],
        };

        return match ($where['operator']) {
            '=', '==' => $left === $right,
            '!=', '<>' => $left !== $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            default => throw UnsupportedQuery::because("the [{$where['operator']}] operator is not supported"),
        };
    }

    private function subquery(Builder $query): self
    {
        if (! $query instanceof self) {
            throw UnsupportedQuery::because('subqueries must use the Realtime Database connection');
        }

        return $query;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sorted(array $rows): array
    {
        $orders = $this->orders ?? [];

        if ($orders === []) {
            return $rows;
        }

        foreach ($orders as $order) {
            if (! isset($order['column']) || ! is_string($order['column'])) {
                throw UnsupportedQuery::because('only column ordering is supported');
            }
        }

        usort($rows, function (array $a, array $b) use ($orders) {
            foreach ($orders as $order) {
                $column = $this->column($order['column']);
                $result = Values::sortCompare($a[$column] ?? null, $b[$column] ?? null);

                if ($result !== 0) {
                    return strtolower($order['direction']) === 'desc' ? -$result : $result;
                }
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sliced(array $rows): array
    {
        $offset = max(0, (int) ($this->offset ?? 0));

        if ($offset === 0 && $this->limit === null) {
            return $rows;
        }

        return array_slice($rows, $offset, $this->limit === null ? null : max(0, (int) $this->limit));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function project(array $row): array
    {
        $columns = $this->columns ?? ['*'];
        $projected = [];

        foreach ($columns as $column) {
            if ($column instanceof Expression) {
                throw UnsupportedQuery::because('raw select expressions are not supported');
            }

            [$name, $alias] = $this->alias((string) $column);

            if ($name === '*' || str_ends_with($name, '.*')) {
                $projected += $row;

                continue;
            }

            $projected[$alias] = $row[$this->column($name)] ?? null;
        }

        return $projected;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function computeAggregate(array $rows): mixed
    {
        $function = strtolower($this->aggregate['function']);
        $column = $this->aggregate['columns'][0] ?? '*';

        if ($column instanceof Expression) {
            throw UnsupportedQuery::because('raw aggregate expressions are not supported');
        }

        [$column] = $this->alias((string) $column);
        $all = $column === '*' || str_ends_with($column, '.*');
        $values = $all ? $rows : array_values(array_filter(
            array_map(fn (array $row) => $row[$this->column($column)] ?? null, $rows),
            fn ($value) => $value !== null,
        ));

        if ($this->distinct && ! $all) {
            $values = array_values(array_unique($values, SORT_REGULAR));
        }

        return match ($function) {
            'count' => count($values),
            'sum' => $values === [] ? null : $this->exactSum($values),
            'avg' => $values === [] ? null : (float) $this->exactSum($values) / count($values),
            'min', 'max' => $this->extreme($values, $function),
            default => throw UnsupportedQuery::because("the [{$function}] aggregate is not supported"),
        };
    }

    /**
     * Exact decimal sum: an int for whole numbers, otherwise a float.
     *
     * @param  list<mixed>  $values
     */
    private function exactSum(array $values): int|float
    {
        $scale = 0;

        foreach ($values as $value) {
            $text = (string) Values::scalar($value);

            if (($dot = strpos($text, '.')) !== false) {
                $scale = max($scale, strlen($text) - $dot - 1);
            }
        }

        $total = '0';

        foreach ($values as $value) {
            $text = is_numeric(Values::scalar($value)) ? (string) Values::scalar($value) : '0';
            $total = bcadd($total, str_contains($text, 'E') || str_contains($text, 'e') ? sprintf('%.10F', (float) $text) : $text, $scale);
        }

        return $scale === 0 ? (int) $total : (float) $total;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function extreme(array $values, string $function): mixed
    {
        $result = null;

        foreach ($values as $value) {
            if ($result === null) {
                $result = $value;

                continue;
            }

            $comparison = Values::sortCompare($value, $result);

            if (($function === 'min' && $comparison < 0) || ($function === 'max' && $comparison > 0)) {
                $result = $value;
            }
        }

        return $result;
    }

    // --------------------------------------------------------------- helpers

    private function store(): TableStore
    {
        return $this->connection->store($this->table());
    }

    private function table(): string
    {
        if (! is_string($this->from) || $this->from === '') {
            throw UnsupportedQuery::because('queries must select from a table');
        }

        [$table] = $this->alias($this->from);

        return $table;
    }

    /**
     * Strip a "table." prefix from a column name.
     */
    private function column(string $column): string
    {
        $position = strrpos($column, '.');

        return $position === false ? $column : substr($column, $position + 1);
    }

    private function isOwnColumn(string $column): bool
    {
        [$table, $alias] = $this->alias((string) $this->from);
        $prefix = substr($column, 0, (int) strrpos($column, '.'));

        return $prefix === $table || $prefix === $alias;
    }

    /**
     * Split "name as alias"; the alias defaults to the bare column name.
     *
     * @return array{0: string, 1: string}
     */
    private function alias(string $expression): array
    {
        if (preg_match('/^\s*(.+?)\s+as\s+(.+?)\s*$/i', $expression, $matches)) {
            return [$matches[1], $matches[2]];
        }

        $expression = trim($expression);

        return [$expression, $this->column($expression)];
    }

    private function bound(mixed $value): mixed
    {
        if ($value instanceof Expression) {
            throw UnsupportedQuery::because('raw expressions and subqueries cannot be compared');
        }

        return $value;
    }

    private function guardAgainstSql(): void
    {
        if ($this->joins) {
            throw UnsupportedQuery::because('joins are not supported; load related records instead');
        }

        if ($this->unions) {
            throw UnsupportedQuery::because('unions are not supported');
        }

        if ($this->groups || $this->havings) {
            throw UnsupportedQuery::because('GROUP BY/HAVING are not supported; group the collection instead');
        }

        if ($this->from instanceof Expression) {
            throw UnsupportedQuery::because('selecting from a subquery is not supported');
        }

        foreach ($this->columns ?? [] as $column) {
            if ($column instanceof Expression) {
                throw UnsupportedQuery::because('raw select expressions are not supported');
            }
        }

        $this->guardWheres($this->wheres);
    }

    /**
     * Reject unsupported where clauses even when no row would reach them.
     *
     * @param  array<int, array<string, mixed>>  $wheres
     */
    private function guardWheres(array $wheres): void
    {
        foreach ($wheres as $where) {
            if (! in_array($where['type'], self::WHERE_TYPES, true)) {
                throw UnsupportedQuery::because("where clauses of type [{$where['type']}] are not supported");
            }

            if (isset($where['column']) && ! is_string($where['column'])) {
                throw UnsupportedQuery::because('where clauses must name a column');
            }

            if ($where['type'] === 'Nested') {
                $this->guardWheres($where['query']->wheres);
            }
        }
    }
}
