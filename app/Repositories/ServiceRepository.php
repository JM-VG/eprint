<?php

namespace App\Repositories;

class ServiceRepository extends Repository
{
    public function table(): string
    {
        return 'services';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function active(): array
    {
        return $this->where(fn (array $service) => (bool) ($service['is_active'] ?? true) === true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ofType(string $type): array
    {
        return $this->whereColumn('type', $type);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeOfType(string $type): array
    {
        return array_values(array_filter(
            $this->active(),
            fn (array $service) => ($service['type'] ?? null) === $type,
        ));
    }
}
