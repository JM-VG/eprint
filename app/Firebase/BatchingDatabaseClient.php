<?php

namespace App\Firebase;

use Throwable;

/**
 * Decorates a DatabaseClient with write batches.
 *
 * Inside a batch, writes are buffered instead of sent. Reads overlay the
 * buffered writes so code sees its own changes. On commit every buffered
 * write is sent as one multi-path update, which the Realtime Database
 * applies atomically; a rollback simply discards the buffer. Nested
 * batches behave like savepoints.
 *
 * Counters and transactions (increment/transaction/push) always go straight
 * to the server: they are atomic on their own and must not be undone.
 */
class BatchingDatabaseClient implements DatabaseClient
{
    /**
     * Buffered writes in order: [path, value]; a null value deletes.
     *
     * @var list<array{0: string, 1: mixed}>
     */
    private array $writes = [];

    /**
     * Buffer length at the start of each open batch level.
     *
     * @var list<int>
     */
    private array $savepoints = [];

    public function __construct(private DatabaseClient $inner) {}

    public function inner(): DatabaseClient
    {
        return $this->inner;
    }

    /**
     * Run the callback inside a batch and commit it when the outermost
     * batch finishes. Any exception rolls the batch level back.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function batch(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback();
        } catch (Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        $this->commit();

        return $result;
    }

    public function begin(): void
    {
        $this->savepoints[] = count($this->writes);
    }

    public function commit(): void
    {
        if ($this->savepoints === []) {
            return;
        }

        array_pop($this->savepoints);

        if ($this->savepoints !== []) {
            return;
        }

        $paths = $this->flatten($this->writes);
        $this->writes = [];

        if ($paths !== []) {
            $this->inner->update('', $paths);
        }
    }

    public function rollBack(): void
    {
        if ($this->savepoints === []) {
            return;
        }

        $this->writes = array_slice($this->writes, 0, array_pop($this->savepoints));
    }

    public function level(): int
    {
        return count($this->savepoints);
    }

    public function inBatch(): bool
    {
        return $this->savepoints !== [];
    }

    public function get(string $path): mixed
    {
        $path = $this->normalize($path);
        $value = $this->inner->get($path);

        foreach ($this->writes as [$written, $writtenValue]) {
            if ($written === $path) {
                $value = $writtenValue;
            } elseif ($path === '' || str_starts_with($written, $path.'/')) {
                $value = $this->setIn($value, $this->relative($written, $path), $writtenValue);
            } elseif ($written === '' || str_starts_with($path, $written.'/')) {
                $value = $this->getIn($writtenValue, $this->relative($path, $written));
            }
        }

        return $this->clean($value);
    }

    public function set(string $path, mixed $value): void
    {
        $this->write($path, $value);
    }

    public function update(string $path, array $values): void
    {
        if ($values === []) {
            return;
        }

        $path = $this->normalize($path);

        $this->writeMany(array_combine(
            array_map(fn ($key) => $this->join($path, (string) $key), array_keys($values)),
            array_values($values),
        ));
    }

    public function push(string $path, mixed $value): string
    {
        return $this->inner->push($path, $value);
    }

    public function remove(string $path): void
    {
        $this->write($path, null);
    }

    public function exists(string $path): bool
    {
        return $this->get($path) !== null;
    }

    public function increment(string $path, int $delta = 1): int
    {
        return $this->inner->increment($path, $delta);
    }

    public function transaction(string $path, callable $callback): mixed
    {
        return $this->inner->transaction($path, $callback);
    }

    private function write(string $path, mixed $value): void
    {
        $this->writeMany([$this->normalize($path) => $value]);
    }

    /**
     * Buffer the writes, then send them at once when no batch is open.
     *
     * @param  array<string, mixed>  $paths
     */
    private function writeMany(array $paths): void
    {
        foreach ($paths as $path => $value) {
            $this->writes[] = [$this->normalize((string) $path), $value];
        }

        if (! $this->inBatch()) {
            $this->begin();
            $this->commit();
        }
    }

    /**
     * Collapse ordered writes into non-overlapping paths for one
     * multi-path update (the server rejects ancestor/descendant pairs).
     *
     * @param  list<array{0: string, 1: mixed}>  $writes
     * @return array<string, mixed>
     */
    private function flatten(array $writes): array
    {
        $paths = [];

        foreach ($writes as [$path, $value]) {
            foreach (array_keys($paths) as $existing) {
                if ($path === '' || str_starts_with((string) $existing, $path.'/')) {
                    unset($paths[$existing]);
                }
            }

            $ancestor = null;

            foreach (array_keys($paths) as $existing) {
                if ($existing === '' || str_starts_with($path, $existing.'/')) {
                    $ancestor = (string) $existing;

                    break;
                }
            }

            if ($ancestor !== null) {
                $paths[$ancestor] = $this->setIn($paths[$ancestor], $this->relative($path, $ancestor), $value);
            } else {
                $paths[$path] = $value;
            }
        }

        if (array_key_exists('', $paths)) {
            return is_array($paths['']) ? $paths[''] : [];
        }

        return $paths;
    }

    private function setIn(mixed $target, string $relative, mixed $value): mixed
    {
        $segments = $this->segments($relative);

        if ($segments === []) {
            return $value;
        }

        $target = is_array($target) ? $target : [];
        $node = &$target;
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if (! isset($node[$segment]) || ! is_array($node[$segment])) {
                $node[$segment] = [];
            }

            $node = &$node[$segment];
        }

        if ($value === null) {
            unset($node[$last]);
        } else {
            $node[$last] = $value;
        }

        unset($node);

        return $target;
    }

    private function getIn(mixed $value, string $relative): mixed
    {
        foreach ($this->segments($relative) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Empty objects do not exist in the Realtime Database.
     */
    private function clean(mixed $value): mixed
    {
        return $value === [] ? null : $value;
    }

    private function relative(string $path, string $base): string
    {
        return $base === '' ? $path : substr($path, strlen($base) + 1);
    }

    private function join(string $path, string $key): string
    {
        return $this->normalize($path === '' ? $key : $path.'/'.$key);
    }

    private function normalize(string $path): string
    {
        return implode('/', $this->segments($path));
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));
    }
}
