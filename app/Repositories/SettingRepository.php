<?php

namespace App\Repositories;

use App\Firebase\DatabaseClient;
use Illuminate\Support\Carbon;

/**
 * Settings are a flat key/value map at "/settings/{key}" rather than rows
 * with ids, matching the key-value shape the application reads.
 */
class SettingRepository
{
    public function __construct(protected DatabaseClient $database) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $settings = $this->database->get('settings');

        return is_array($settings) ? $settings : [];
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->database->get('settings/'.$key);

        return ($value !== null && $value !== '') ? (string) $value : $default;
    }

    public function set(string $key, ?string $value): void
    {
        $this->database->set('settings/'.$key, $value);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        $this->database->update('settings', $values);
    }

    public function forget(string $key): void
    {
        $this->database->remove('settings/'.$key);
    }

    public function reset(): void
    {
        $this->database->remove('settings');
    }

    public function has(string $key): bool
    {
        return $this->database->exists('settings/'.$key);
    }

    /**
     * Insert missing keys only, so admin edits survive re-seeding.
     *
     * @param  array<string, mixed>  $defaults
     */
    public function firstOrCreateMany(array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            if (! $this->has($key)) {
                $this->set($key, $value === null ? null : (string) $value);
            }
        }
    }

    public function updatedAt(): ?string
    {
        $value = $this->database->get('settings');

        return is_array($value) ? ($value['_updated_at'] ?? null) : null;
    }

    public function touch(): void
    {
        $this->database->set('settings/_updated_at', Carbon::now()->toIso8601String());
    }
}
