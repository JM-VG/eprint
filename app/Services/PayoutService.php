<?php

namespace App\Services;

use App\Models\CommissionPayout;
use App\Models\Sale;
use App\Models\User;
use App\Realtime\Atomic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use RuntimeException;

class PayoutService
{
    /**
     * Unpaid commission for the staff member in the date range (inclusive, whole days).
     *
     * @return array{amount: string, count: int}
     */
    public function preview(User $staff, string $from, string $to): array
    {
        $sales = $this->unpaid($staff, $from, $to)->get(['commission_amount']);

        return [
            'amount' => $this->total($sales),
            'count' => $sales->count(),
        ];
    }

    /**
     * Pay out every unpaid sale in the range: create the payout and link the sales in one atomic write.
     * The staff member's payouts are locked so a double submit cannot pay the same sale twice.
     *
     * @throws RuntimeException when there is nothing unpaid in the range
     */
    public function create(User $staff, User $admin, string $from, string $to, ?string $notes = null): CommissionPayout
    {
        return Atomic::run(function () use ($staff, $admin, $from, $to, $notes) {
            $sales = $this->unpaid($staff, $from, $to)->get(['id', 'commission_amount']);

            $amount = $this->total($sales);

            if (bccomp($amount, '0.00', 2) <= 0) {
                throw new RuntimeException('There is no unpaid commission for this staff member in that range.');
            }

            $payout = CommissionPayout::create([
                'user_id' => $staff->id,
                'paid_by' => $admin->id,
                'period_start' => $from,
                'period_end' => $to,
                'amount' => $amount,
                'notes' => $notes,
                'paid_at' => now(),
            ]);

            Sale::whereIn('id', $sales->pluck('id'))->whereNull('payout_id')->update(['payout_id' => $payout->id]);

            return $payout;
        }, ["payouts/{$staff->id}"]);
    }

    /**
     * Exact commission sum of the given sales.
     *
     * @param  Collection<int, Sale>  $sales
     */
    private function total(Collection $sales): string
    {
        return $sales->reduce(fn (string $sum, Sale $sale) => bcadd($sum, (string) $sale->commission_amount, 2), '0.00');
    }

    /**
     * @return Builder<Sale>
     */
    private function unpaid(User $staff, string $from, string $to): Builder
    {
        return Sale::where('user_id', $staff->id)
            ->whereNull('payout_id')
            ->where('created_at', '>=', Carbon::parse($from)->startOfDay())
            ->where('created_at', '<=', Carbon::parse($to)->endOfDay());
    }
}
