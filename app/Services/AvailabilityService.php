<?php

namespace App\Services;

use App\Enums\CloseReason;
use App\Models\Location;
use App\Models\StationSession;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityService
{
    /**
     * A station counts as open while its last heartbeat is at most this old.
     */
    public const IDLE_MINUTES = 5;

    /**
     * Every location with its open flag and number of staff on duty.
     *
     * @return list<array{id: int, name: string, slug: string, open: bool, staff_count: int}>
     */
    public function locations(): array
    {
        $staffCounts = $this->activeSessions()
            ->selectRaw('location_id, COUNT(DISTINCT user_id) as staff_count')
            ->groupBy('location_id')
            ->pluck('staff_count', 'location_id');

        return Location::orderBy('id')->get()->map(fn (Location $location) => [
            'id' => $location->id,
            'name' => $location->name,
            'slug' => $location->slug,
            'open' => ($staffCounts[$location->id] ?? 0) > 0,
            'staff_count' => (int) ($staffCounts[$location->id] ?? 0),
        ])->all();
    }

    public function isOpen(Location $location): bool
    {
        return $this->activeSessions()->where('location_id', $location->id)->exists();
    }

    /**
     * Close sessions whose last heartbeat is older than the idle window.
     */
    public function closeIdle(): int
    {
        return StationSession::whereNull('closed_at')
            ->where('last_seen_at', '<', $this->cutoff())
            ->update(['closed_at' => now(), 'close_reason' => CloseReason::Timeout, 'updated_at' => now()]);
    }

    /**
     * @return Builder<StationSession>
     */
    private function activeSessions(): Builder
    {
        return StationSession::query()
            ->whereNull('closed_at')
            ->where('last_seen_at', '>=', $this->cutoff());
    }

    private function cutoff(): DateTimeInterface
    {
        return now()->subMinutes(self::IDLE_MINUTES);
    }
}
