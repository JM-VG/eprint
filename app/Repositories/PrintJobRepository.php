<?php

namespace App\Repositories;

class PrintJobRepository extends Repository
{
    public function table(): string
    {
        return 'print_jobs';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByClaimCode(string $claimCode): ?array
    {
        return $this->firstWhere('claim_code', mb_strtoupper(trim($claimCode)));
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
    public function forLocation(int|string $locationId): array
    {
        return $this->whereColumn('location_id', $locationId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function withStatus(string $status): array
    {
        return $this->whereColumn('status', $status);
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array<int, array<string, mixed>>
     */
    public function withStatusIn(array $statuses): array
    {
        return $this->whereIn('status', $statuses);
    }
}
