<?php

namespace App\Firebase;

/**
 * Minimal abstraction over a Firebase Realtime Database JSON tree.
 *
 * Values are plain PHP scalars/arrays so the rest of the application never
 * has to know whether it is talking to the live REST API or the in-memory
 * fake used by tests.
 */
interface DatabaseClient
{
    /**
     * Read the value stored at a path, or null when the path does not exist.
     */
    public function get(string $path): mixed;

    /**
     * Overwrite the value at a path.
     */
    public function set(string $path, mixed $value): void;

    /**
     * Merge the given keys into the object stored at a path.
     *
     * A key may itself be a relative path ("a/b"); it is updated in place
     * without disturbing sibling keys, mirroring the Realtime Database PATCH
     * semantics.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(string $path, array $values): void;

    /**
     * Append a value under a server generated key and return that key.
     */
    public function push(string $path, mixed $value): string;

    /**
     * Delete the value at a path.
     */
    public function remove(string $path): void;

    /**
     * Whether a non-null value exists at a path.
     */
    public function exists(string $path): bool;

    /**
     * Atomically add to an integer stored at a path and return the new value.
     */
    public function increment(string $path, int $delta = 1): int;

    /**
     * Atomically modify the value stored at a path.
     *
     * The callback receives the current value (null when missing) and must
     * return the new value. The operation is retried when another client
     * races to change the same path first.
     */
    public function transaction(string $path, callable $callback): mixed;
}
