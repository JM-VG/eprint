<?php

namespace App\Services;

use App\Models\Location;
use App\Models\StationSession;
use App\Models\User;

class StationService
{
    /**
     * The user's open station session, if any.
     */
    public function current(User $user): ?StationSession
    {
        return StationSession::where('user_id', $user->id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open a station session at the location. Returns null when the user is already clocked in.
     */
    public function clockIn(User $user, Location $location): ?StationSession
    {
        if ($this->current($user)) {
            return null;
        }

        return StationSession::create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
