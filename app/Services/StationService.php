<?php

namespace App\Services;

use App\Enums\CloseReason;
use App\Models\Location;
use App\Models\StationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            ->latest('id')
            ->first();
    }

    /**
     * Open a station session at the location. Returns null when the user is already clocked in.
     * The user row is locked so a double submit cannot open two sessions.
     */
    public function clockIn(User $user, Location $location): ?StationSession
    {
        return DB::transaction(function () use ($user, $location) {
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($this->current($user)) {
                return null;
            }

            return $this->open($user, $location);
        });
    }

    /**
     * Close every open session of the user and open one at the new location.
     */
    public function switchTo(User $user, Location $location): StationSession
    {
        return DB::transaction(function () use ($user, $location) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $this->close($user, CloseReason::Switch);

            return $this->open($user, $location);
        });
    }

    /**
     * Close every open session of the user. Returns how many were closed.
     */
    public function close(User $user, CloseReason $reason): int
    {
        return StationSession::where('user_id', $user->id)
            ->whereNull('closed_at')
            ->update(['closed_at' => now(), 'close_reason' => $reason, 'updated_at' => now()]);
    }

    /**
     * Mark the user's open session as seen now.
     */
    public function heartbeat(User $user): ?StationSession
    {
        $session = $this->current($user);
        $session?->update(['last_seen_at' => now()]);

        return $session;
    }

    private function open(User $user, Location $location): StationSession
    {
        return StationSession::create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
