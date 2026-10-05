<?php

namespace App\Repositories;

class SaleRepository extends Repository
{
    public function table(): string
    {
        return 'sales';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(int|string $userId): array
    {
        return $this->whereColumn('user_id', $userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function unpaidForUser(int|string $userId): array
    {
        return $this->where(
            fn (array $sale) => $this->matches($sale, 'user_id', $userId) && ($sale['payout_id'] ?? null) === null,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forLocation(int|string $locationId): array
    {
        return $this->whereColumn('location_id', $locationId);
    }
}
