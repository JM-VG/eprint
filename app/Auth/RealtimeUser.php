<?php

namespace App\Auth;

use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Carbon;
use JsonSerializable;

/**
 * A user record loaded from the Firebase Realtime Database.
 *
 * Keeps the attribute surface the application expects (enum casts, dates)
 * without depending on Eloquent.
 */
class RealtimeUser implements AuthenticatableContract, JsonSerializable
{
    use Authenticatable;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(protected array $attributes) {}

    public function __get(string $name): mixed
    {
        return $this->attribute($name);
    }

    public function __isset(string $name): bool
    {
        return ($this->attributes[$name] ?? null) !== null;
    }

    public function attribute(string $name): mixed
    {
        return $this->cast($name, $this->attributes[$name] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function getKey(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    private function cast(string $name, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($name) {
            'role' => Role::from((string) $value),
            'status' => UserStatus::from((string) $value),
            'must_change_password' => (bool) $value,
            'approved_at' => Carbon::parse($value),
            default => $value,
        };
    }
}
