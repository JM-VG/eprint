<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Models\Location;
use App\Models\PrintJob;
use Illuminate\Database\Eloquent\Collection;

class QueueService
{
    /**
     * Statuses that still need staff attention.
     *
     * @var list<JobStatus>
     */
    public const ACTIVE = [JobStatus::Pending, JobStatus::Accepted, JobStatus::Printing, JobStatus::Ready];

    /**
     * Active jobs at a location, oldest first.
     *
     * @return Collection<int, PrintJob>
     */
    public function forLocation(Location $location): Collection
    {
        return PrintJob::with(['user', 'service'])
            ->where('location_id', $location->id)
            ->whereIn('status', self::ACTIVE)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Pending jobs per location id, including locations with none.
     *
     * @return array<int, int>
     */
    public function pendingCounts(): array
    {
        $counts = PrintJob::where('status', JobStatus::Pending)
            ->selectRaw('location_id, COUNT(*) as total')
            ->groupBy('location_id')
            ->pluck('total', 'location_id');

        return Location::orderBy('id')->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => (int) ($counts[$id] ?? 0)])
            ->all();
    }
}
