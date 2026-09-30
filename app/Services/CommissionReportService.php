<?php

namespace App\Services;

use App\Models\JobLog;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CommissionReportService
{
    /**
     * @return array{today: string, week: string, month: string, all_time: string, unpaid: string, jobs_handled: int}
     */
    public function summary(User $staff): array
    {
        $now = now();

        return [
            'today' => $this->sum($staff, fn (Builder $q) => $q->where('created_at', '>=', $now->copy()->startOfDay())),
            'week' => $this->sum($staff, fn (Builder $q) => $q->where('created_at', '>=', $now->copy()->startOfWeek())),
            'month' => $this->sum($staff, fn (Builder $q) => $q->where('created_at', '>=', $now->copy()->startOfMonth())),
            'all_time' => $this->sum($staff),
            'unpaid' => $this->sum($staff, fn (Builder $q) => $q->whereNull('payout_id')),
            'jobs_handled' => JobLog::where('user_id', $staff->id)
                ->whereNotIn('print_job_id', PrintJob::select('id')->where('user_id', $staff->id))
                ->distinct()
                ->count('print_job_id'),
        ];
    }

    /**
     * Own sales, newest first, with optional date range and location filters.
     *
     * @param  array{from?: ?string, to?: ?string, location_id?: ?int}  $filters
     */
    public function sales(User $staff, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Sale::with(['location', 'printJob'])
            ->where('user_id', $staff->id)
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function sum(User $staff, ?callable $scope = null): string
    {
        $query = Sale::where('user_id', $staff->id);

        if ($scope) {
            $scope($query);
        }

        return number_format((float) $query->sum('commission_amount'), 2, '.', '');
    }
}
