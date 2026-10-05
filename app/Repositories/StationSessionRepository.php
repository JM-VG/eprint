<?php

namespace App\Repositories;

class StationSessionRepository extends Repository
{
    public function table(): string
    {
        return 'station_sessions';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openForUser(int|string $userId): ?array
    {
        foreach ($this->all() as $session) {
            if (($session['closed_at'] ?? null) === null && $this->matches($session, 'user_id', $userId)) {
                return $session;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function open(): array
    {
        return $this->whereNull('closed_at');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openAtLocation(int|string $locationId): array
    {
        return $this->where(
            fn (array $session) => ($session['closed_at'] ?? null) === null
                && $this->matches($session, 'location_id', $locationId),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(int|string $userId): array
    {
        return $this->whereColumn('user_id', $userId);
    }
}
