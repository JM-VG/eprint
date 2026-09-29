<?php

namespace App\Services;

use App\Models\StationSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ShiftHistoryService
{
    /**
     * Station sessions matching the filters, newest first.
     *
     * @param  array{user_id?: ?int, location_id?: ?int, from?: ?string, to?: ?string}  $filters
     */
    public function search(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return StationSession::query()
            ->with(['user', 'location'])
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('opened_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('opened_at', '<=', Carbon::parse($to)->endOfDay()))
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Human duration such as "2h 30m" or "45m".
     */
    public static function duration(StationSession $session): string
    {
        $minutes = (int) $session->opened_at->diffInMinutes($session->closed_at ?? now());

        return $minutes >= 60
            ? intdiv($minutes, 60).'h '.($minutes % 60).'m'
            : "{$minutes}m";
    }
}
