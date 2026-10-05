<?php

namespace App\Repositories;

use App\Firebase\DatabaseClient;
use Illuminate\Support\Carbon;

/**
 * Base class for the Realtime Database backed repositories.
 *
 * Each table is stored as a flat JSON object at "/{table}/{id}". Integer ids
 * are handed out by an atomic counter at "/counters/{table}" so the rest of
 * the application can keep using the same foreign keys it always did.
 */
abstract class Repository
{
    public function __construct(protected DatabaseClient $database) {}

    /**
     * The Realtime Database node backing this repository.
     */
    abstract public function table(): string;

    protected function path(string $suffix = ''): string
    {
        return $suffix === '' ? $this->table() : $this->table().'/'.ltrim($suffix, '/');
    }

    protected function nextId(): int
    {
        return $this->database->increment('counters/'.$this->table());
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    public function all(): array
    {
        $data = $this->database->get($this->path());

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int|string $id): ?array
    {
        $record = $this->database->get($this->path((string) $id));

        return is_array($record) ? $record : null;
    }

    public function exists(int|string $id): bool
    {
        return $this->database->exists($this->path((string) $id));
    }

    /**
     * Create a record, assigning the next integer id when one is not given.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        $id = $attributes['id'] ?? $this->nextId();
        $now = Carbon::now()->toIso8601String();

        $record = array_merge(['created_at' => $now], $attributes);
        $record['id'] = is_numeric($id) ? (int) $id : $id;
        $record['created_at'] ??= $now;
        $record['updated_at'] = $now;

        $this->database->set($this->path((string) $id), $record);

        return $record;
    }

    /**
     * Merge the given attributes into an existing record.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function update(int|string $id, array $attributes): ?array
    {
        if (! $this->exists($id)) {
            return null;
        }

        $attributes['updated_at'] = Carbon::now()->toIso8601String();

        $this->database->update($this->path((string) $id), $attributes);

        return $this->find($id);
    }

    public function delete(int|string $id): bool
    {
        if (! $this->exists($id)) {
            return false;
        }

        $this->database->remove($this->path((string) $id));

        return true;
    }

    /**
     * Filter all records in memory. The dataset for a printing room is small
     * enough that this is simpler and safer than replicating SQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function where(callable $callback): array
    {
        return array_values(array_filter($this->all(), $callback));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function firstWhere(string $column, mixed $value): ?array
    {
        foreach ($this->all() as $record) {
            if ($this->matches($record, $column, $value)) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function whereColumn(string $column, mixed $value): array
    {
        return $this->where(fn (array $record) => $this->matches($record, $column, $value));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, array<string, mixed>>
     */
    public function whereIn(string $column, array $values): array
    {
        return $this->where(function (array $record) use ($column, $values) {
            foreach ($values as $value) {
                if ($this->matches($record, $column, $value)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function whereNull(string $column): array
    {
        return $this->where(fn (array $record) => ($record[$column] ?? null) === null);
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function sum(string $column): float
    {
        $total = 0.0;

        foreach ($this->all() as $record) {
            $total += (float) ($record[$column] ?? 0);
        }

        return $total;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function pluck(string $value, ?string $key = null): array
    {
        $result = [];

        foreach ($this->all() as $record) {
            if ($key === null) {
                $result[] = $record[$value] ?? null;

                continue;
            }

            $result[$record[$key] ?? ''] = $record[$value] ?? null;
        }

        return $result;
    }

    /**
     * Write a complete record exactly as given (used by the importer to
     * preserve original ids and timestamps).
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function put(array $record): array
    {
        if (! isset($record['id'])) {
            throw new \InvalidArgumentException("A record written to [{$this->table()}] must have an id.");
        }

        $this->database->set($this->path((string) $record['id']), $record);

        return $record;
    }

    /**
     * Force the id counter so imported ids are never handed out again.
     */
    public function setNextId(int $value): void
    {
        $this->database->set('counters/'.$this->table(), $value);
    }

    /**
     * Delete every record in the table and reset its id counter.
     */
    public function truncate(): void
    {
        $this->database->remove($this->table());
        $this->database->remove('counters/'.$this->table());
    }

    /**
     * Compare a record column with a value, treating numeric strings and
     * integers as equal so form input and stored ids line up.
     *
     * @param  array<string, mixed>  $record
     */
    protected function matches(array $record, string $column, mixed $value): bool
    {
        $actual = $record[$column] ?? null;

        if ($value === null) {
            return $actual === null;
        }

        if (is_numeric($actual) && is_numeric($value)) {
            return (float) $actual === (float) $value;
        }

        return $actual === $value;
    }
}
