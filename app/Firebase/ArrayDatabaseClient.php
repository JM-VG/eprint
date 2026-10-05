<?php

namespace App\Firebase;

/**
 * In-memory implementation of the Realtime Database tree.
 *
 * Used by the test suite (and optionally for local work without a service
 * account). It mirrors the semantics of the REST client closely enough that
 * repositories and services behave the same against both.
 */
class ArrayDatabaseClient implements DatabaseClient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data = []) {}

    public function get(string $path): mixed
    {
        $node = $this->data;

        foreach ($this->segments($path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    public function set(string $path, mixed $value): void
    {
        $segments = $this->segments($path);

        if ($segments === []) {
            $this->data = is_array($value) ? $value : ['value' => $value];

            return;
        }

        $node = &$this->data;

        foreach ($segments as $segment) {
            if (! isset($node[$segment]) || ! is_array($node[$segment])) {
                $node[$segment] = [];
            }

            $node = &$node[$segment];
        }

        $node = $value;
        unset($node);
    }

    public function update(string $path, array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($this->join($path, (string) $key), $value);
        }
    }

    public function push(string $path, mixed $value): string
    {
        $key = '-'.str_pad((string) ++self::$pushSequence, 10, '0', STR_PAD_LEFT);
        $this->set($this->join($path, $key), $value);

        return $key;
    }

    public function remove(string $path): void
    {
        $segments = $this->segments($path);

        if ($segments === []) {
            $this->data = [];

            return;
        }

        $last = array_pop($segments);
        $node = &$this->data;

        foreach ($segments as $segment) {
            if (! isset($node[$segment]) || ! is_array($node[$segment])) {
                return;
            }

            $node = &$node[$segment];
        }

        if (is_array($node)) {
            unset($node[$last]);
        }

        unset($node);
    }

    public function exists(string $path): bool
    {
        return $this->get($path) !== null;
    }

    public function increment(string $path, int $delta = 1): int
    {
        return (int) $this->transaction($path, fn ($current) => ((int) ($current ?? 0)) + $delta);
    }

    public function transaction(string $path, callable $callback): mixed
    {
        $value = $callback($this->get($path));
        $this->set($path, $value);

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));
    }

    private function join(string $path, string $segment): string
    {
        return trim($path, '/') === '' ? $segment : trim($path, '/').'/'.$segment;
    }

    private static int $pushSequence = 0;
}
