<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleSource;
use App\Models\GcashPayment;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalkinItem;
use Illuminate\Support\Facades\DB;

/**
 * Records money: claims at pickup and (P6-02) walk-in sales.
 * Credit: online job -> staff who received payment; staff_job -> staff who submitted it; walk-in -> staff who entered it.
 */
class CommissionService
{
    public function __construct(private JobStatusService $statuses) {}

    /**
     * Claim a ready job: mark it claimed and paid, create the sale (and GCash payment) in one transaction.
     */
    public function claim(PrintJob $job, User $receiver, PaymentMethod $method, ?string $referenceNo = null): Sale
    {
        return DB::transaction(function () use ($job, $receiver, $method, $referenceNo) {
            $amount = number_format((float) ($job->final_total ?? $job->estimated_total ?? 0), 2, '.', '');
            $note = $method === PaymentMethod::Gcash ? "Paid by GCash, ref {$referenceNo}" : 'Paid in cash';

            $job = $this->statuses->transition($job, JobStatus::Claimed, $receiver, $note, [
                'payment_method' => $method,
                'payment_status' => PaymentStatus::Paid,
                'received_by' => $receiver->id,
                'claimed_at' => now(),
            ]);

            $sale = $this->recordSale(
                creditedTo: $job->source === SaleSource::StaffJob ? $job->user_id : $receiver->id,
                locationId: $job->location_id,
                source: $job->source,
                method: $method,
                amount: $amount,
                printJobId: $job->id,
            );

            if ($method === PaymentMethod::Gcash) {
                GcashPayment::create([
                    'reference_no' => $referenceNo,
                    'amount' => $amount,
                    'print_job_id' => $job->id,
                    'sale_id' => $sale->id,
                    'received_by' => $receiver->id,
                ]);
            }

            return $sale;
        });
    }

    /**
     * Record a walk-in sale with its line items. Credited to the staff who entered it, at their station.
     * Each line keeps the price list unit price as a snapshot; the amount may be edited by staff.
     *
     * @param  list<array{service_id: int, quantity: int, sheets_used: int, amount: string}>  $items
     */
    public function recordWalkin(User $staff, int $locationId, array $items, PaymentMethod $method, ?string $referenceNo = null): Sale
    {
        return DB::transaction(function () use ($staff, $locationId, $items, $method, $referenceNo) {
            $total = array_reduce($items, fn (string $sum, array $item) => bcadd($sum, (string) $item['amount'], 2), '0.00');

            $sale = $this->recordSale(
                creditedTo: $staff->id,
                locationId: $locationId,
                source: SaleSource::Walkin,
                method: $method,
                amount: $total,
            );

            $prices = Service::whereIn('id', array_column($items, 'service_id'))->pluck('price', 'id');

            foreach ($items as $item) {
                WalkinItem::create([
                    'sale_id' => $sale->id,
                    'service_id' => $item['service_id'],
                    'quantity' => (int) $item['quantity'],
                    'sheets_used' => (int) $item['sheets_used'],
                    'unit_price' => $prices[$item['service_id']],
                    'amount' => number_format((float) $item['amount'], 2, '.', ''),
                ]);
            }

            if ($method === PaymentMethod::Gcash) {
                GcashPayment::create([
                    'reference_no' => $referenceNo,
                    'amount' => $total,
                    'sale_id' => $sale->id,
                    'received_by' => $staff->id,
                ]);
            }

            return $sale;
        });
    }

    /**
     * commission_amount = round(amount x rate / 100, 2), half up.
     */
    public function commission(string $amount, string $rate): string
    {
        $raw = bcdiv(bcmul($amount, $rate, 6), '100', 6);

        return bcadd($raw, '0.005', 2);
    }

    public function currentRate(): string
    {
        return number_format((float) Setting::read('commission_rate', '0'), 2, '.', '');
    }

    /**
     * Create a sale with the current commission rate snapshot.
     */
    public function recordSale(int $creditedTo, int $locationId, SaleSource $source, PaymentMethod $method, string $amount, ?int $printJobId = null, ?string $rate = null): Sale
    {
        $rate = $rate !== null ? number_format((float) $rate, 2, '.', '') : $this->currentRate();

        return Sale::create([
            'user_id' => $creditedTo,
            'print_job_id' => $printJobId,
            'location_id' => $locationId,
            'source' => $source,
            'payment_method' => $method,
            'amount' => $amount,
            'commission_rate' => $rate,
            'commission_amount' => $this->commission($amount, $rate),
        ]);
    }
}
