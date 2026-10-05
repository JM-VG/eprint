<?php

namespace App\Repositories;

class GcashPaymentRepository extends Repository
{
    public function table(): string
    {
        return 'gcash_payments';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReference(string $referenceNo): ?array
    {
        return $this->firstWhere('reference_no', trim($referenceNo));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forSale(int|string $saleId): ?array
    {
        return $this->firstWhere('sale_id', $saleId);
    }
}
