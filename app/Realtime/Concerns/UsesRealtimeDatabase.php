<?php

namespace App\Realtime\Concerns;

/**
 * Stores an Eloquent model in the Firebase Realtime Database through the
 * "rtdb" connection.
 */
trait UsesRealtimeDatabase
{
    public function getConnectionName()
    {
        return $this->connection ?? 'rtdb';
    }
}
