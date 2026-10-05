<?php

namespace App\Repositories;

class LocationRepository extends Repository
{
    public function table(): string
    {
        return 'locations';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->firstWhere('slug', $slug);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ordered(): array
    {
        $locations = $this->all();
        ksort($locations);

        return array_values($locations);
    }
}
