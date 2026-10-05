<?php

namespace App\Realtime\Database\Stores;

use App\Repositories\SettingRepository;

/**
 * The settings table, stored as a key/value map at "/settings/{key}".
 * Keys starting with an underscore are bookkeeping, not settings.
 */
class SettingStore implements TableStore
{
    public function __construct(private SettingRepository $settings) {}

    public function keyName(): string
    {
        return 'key';
    }

    public function rows(): array
    {
        $rows = [];

        foreach ($this->settings->all() as $key => $value) {
            if (! str_starts_with((string) $key, '_') && ! is_array($value)) {
                $rows[(string) $key] = $this->row((string) $key, $value);
            }
        }

        ksort($rows);

        return $rows;
    }

    public function find(int|string $key): ?array
    {
        $key = (string) $key;

        if ($key === '' || str_starts_with($key, '_') || ! $this->settings->has($key)) {
            return null;
        }

        return $this->row($key, $this->settings->all()[$key] ?? null);
    }

    public function insert(array $row): int|string
    {
        $key = (string) $row['key'];
        $this->write($key, $row['value'] ?? null);

        return $key;
    }

    public function patch(int|string $key, array $values): void
    {
        $key = (string) $key;
        $value = array_key_exists('value', $values) ? $values['value'] : ($this->find($key)['value'] ?? null);
        $newKey = array_key_exists('key', $values) ? (string) $values['key'] : $key;

        if ($newKey !== $key) {
            $this->settings->forget($key);
        }

        $this->write($newKey, $value);
    }

    public function delete(int|string $key): void
    {
        $this->settings->forget((string) $key);
        $this->settings->touch();
    }

    public function truncate(): void
    {
        $this->settings->reset();
    }

    /**
     * A setting stored as null would not exist, so null is kept as "".
     */
    private function write(string $key, mixed $value): void
    {
        $this->settings->set($key, $value === null ? '' : (string) $value);
        $this->settings->touch();
    }

    /**
     * @return array{key: string, value: ?string}
     */
    private function row(string $key, mixed $value): array
    {
        return ['key' => $key, 'value' => $value === null ? null : (string) $value];
    }
}
