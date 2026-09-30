<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\WalkinItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Sales report: timeframe (today, week, month, custom), officer, location and source filters.
 * Sheets used = the claimed job's sheets_used plus walk-in line item sheets.
 */
class SalesReportService
{
    /**
     * @param  array<string, mixed>  $filters  validated SalesReportRequest data
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(array $filters): array
    {
        $now = now();

        return match ($filters['timeframe'] ?? 'month') {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'custom' => [Carbon::parse($filters['from'])->startOfDay(), Carbon::parse($filters['to'])->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale>
     */
    public function query(array $filters): Builder
    {
        [$from, $to] = $this->range($filters);

        return Sale::query()
            ->whereBetween('sales.created_at', [$from, $to])
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('sales.user_id', $id))
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('sales.location_id', $id))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('sales.source', $source));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{amount: string, commission: string, sheets: int, count: int}
     */
    public function totals(array $filters): array
    {
        $sales = $this->query($filters)->select('sales.id', 'sales.amount', 'sales.commission_amount')->get();

        $jobSheets = (int) $this->query($filters)
            ->join('print_jobs', 'print_jobs.id', '=', 'sales.print_job_id')
            ->sum('print_jobs.sheets_used');

        $walkinSheets = (int) WalkinItem::whereIn('sale_id', $this->query($filters)->select('sales.id'))->sum('sheets_used');

        return [
            'amount' => $sales->reduce(fn (string $sum, Sale $s) => bcadd($sum, (string) $s->amount, 2), '0.00'),
            'commission' => $sales->reduce(fn (string $sum, Sale $s) => bcadd($sum, (string) $s->commission_amount, 2), '0.00'),
            'sheets' => $jobSheets + $walkinSheets,
            'count' => $sales->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function rows(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->withDetails($this->query($filters))->paginate($perPage)->withQueryString();
    }

    /**
     * Rows for the CSV export, streamed in chunks.
     *
     * @param  array<string, mixed>  $filters
     * @return iterable<Sale>
     */
    public function exportRows(array $filters): iterable
    {
        return $this->withDetails($this->query($filters))->lazy(500);
    }

    /**
     * Sheets used by one sale.
     */
    public static function sheets(Sale $sale): int
    {
        return (int) ($sale->printJob?->sheets_used ?? 0) + (int) $sale->items->sum('sheets_used');
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    private function withDetails(Builder $query): Builder
    {
        return $query->with(['user', 'location', 'printJob', 'items'])
            ->orderByDesc('sales.created_at')
            ->orderByDesc('sales.id');
    }
}
