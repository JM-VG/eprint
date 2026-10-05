<?php

namespace App\Repositories;

class CommissionPayoutRepository extends Repository
{
    public function table(): string
    {
        return 'commission_payouts';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forStaff(int|string $userId): array
    {
        return $this->whereColumn('user_id', $userId);
    }
}
