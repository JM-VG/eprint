<?php

namespace App\Services;

use App\Models\JobLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AuditLogService
{
    /**
     * Job log entries, newest first, filtered by claim code, actor, target status and date range.
     *
     * @param  array{code?: ?string, user_id?: ?int, status?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function search(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return JobLog::with(['printJob', 'user'])
            ->when($filters['code'] ?? null, fn ($q, $code) => $q->whereHas('printJob', fn ($j) => $j->where('claim_code', strtoupper(trim($code)))))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('to_status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
