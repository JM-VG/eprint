<?php

namespace App\Repositories;

class WalkinItemRepository extends Repository
{
    public function table(): string
    {
        return 'walkin_items';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forSale(int|string $saleId): array
    {
        return $this->whereColumn('sale_id', $saleId);
    }
}
