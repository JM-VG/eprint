<?php

namespace App\Realtime\Database\Stores;

use App\Repositories\Repository;

/**
 * A table stored as flat records at "/{table}/{id}" through its repository.
 */
class RecordStore implements TableStore
{
    /**
     * @param  list<string>  $columns  known columns, filled with null when missing
     */
    public function __construct(private Repository $repository, private array $columns = []) {}

    public function keyName(): string
    {
        return 'id';
    }

    public function rows(): array
    {
        $rows = [];

        foreach ($this->repository->all() as $key => $record) {
            $rows[$key] = $this->hydrate($key, $record);
        }

        ksort($rows);

        return $rows;
    }

    public function find(int|string $key): ?array
    {
        if (! $this->isValidKey($key)) {
            return null;
        }

        $record = $this->repository->find($key);

        return $record === null ? null : $this->hydrate($key, $record);
    }

    public function insert(array $row): int|string
    {
        $key = $row['id'] ?? null;

        if ($key === null || $key === '') {
            $key = $this->repository->allocateId();
        }

        $row['id'] = is_numeric($key) && (string) (int) $key === (string) $key ? (int) $key : $key;
        $this->repository->put(array_filter($row, fn ($value) => $value !== null));

        return $row['id'];
    }

    public function patch(int|string $key, array $values): void
    {
        $this->repository->patch($key, $values);
    }

    public function delete(int|string $key): void
    {
        $this->repository->remove($key);
    }

    public function truncate(): void
    {
        $this->repository->truncate();
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function hydrate(int|string $key, array $record): array
    {
        $record['id'] ??= $key;

        if ($this->columns === []) {
            return $record;
        }

        $row = [];

        foreach ($this->columns as $column) {
            $row[$column] = $record[$column] ?? null;
        }

        return $row + $record;
    }

    /**
     * Keys that can never be a database path segment cannot match a record.
     */
    private function isValidKey(int|string $key): bool
    {
        return $key !== '' && preg_match('/[.#$\[\]\/]/', (string) $key) !== 1;
    }
}
