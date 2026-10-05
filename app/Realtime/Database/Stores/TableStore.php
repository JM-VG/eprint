<?php

namespace App\Realtime\Database\Stores;

/**
 * How one table's rows are laid out in the Realtime Database.
 */
interface TableStore
{
    /**
     * The column that identifies a row (its key in the database).
     */
    public function keyName(): string;

    /**
     * Every row, keyed by its key, in key order.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function rows(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(int|string $key): ?array;

    /**
     * Write a new row, assigning a key when the row has none.
     *
     * @param  array<string, mixed>  $row
     */
    public function insert(array $row): int|string;

    /**
     * @param  array<string, mixed>  $values
     */
    public function patch(int|string $key, array $values): void;

    public function delete(int|string $key): void;

    public function truncate(): void;
}
