<?php

namespace App\Repositories;

use Illuminate\Support\Carbon;

class AnnouncementRepository extends Repository
{
    public function table(): string
    {
        return 'announcements';
    }

    /**
     * Announcements whose window includes now; a missing start or end is
     * open-ended.
     *
     * @return array<int, array<string, mixed>>
     */
    public function active(?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        return $this->where(function (array $announcement) use ($now) {
            $startsAt = $announcement['starts_at'] ?? null;
            $endsAt = $announcement['ends_at'] ?? null;

            $started = $startsAt === null || Carbon::parse($startsAt)->lessThanOrEqualTo($now);
            $notEnded = $endsAt === null || Carbon::parse($endsAt)->greaterThanOrEqualTo($now);

            return $started && $notEnded;
        });
    }
}
