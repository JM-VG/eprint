<?php

use App\Enums\CloseReason;
use App\Models\Location;
use App\Models\StationSession;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->freezeSecond();
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
});

function seenAgo(Location $location, int $seconds, array $attributes = []): StationSession
{
    return StationSession::factory()->for($location)->create(array_merge([
        'last_seen_at' => now()->subSeconds($seconds),
    ], $attributes));
}

function availabilityFor(Location $location): array
{
    return collect(app(AvailabilityService::class)->locations())->firstWhere('id', $location->id);
}

describe('AvailabilityService', function () {
    it('lists every location with its open flag and staff count', function () {
        seenAgo($this->main, 10);

        $result = app(AvailabilityService::class)->locations();

        expect($result)->toHaveCount(2)
            ->and(availabilityFor($this->main))->toMatchArray(['name' => 'Main IT Printing Room', 'open' => true, 'staff_count' => 1])
            ->and(availabilityFor($this->ccs))->toMatchArray(['name' => 'CCS Office', 'open' => false, 'staff_count' => 0]);
    });

    it('counts a session seen exactly 5 minutes ago as open', function () {
        seenAgo($this->main, 300);

        expect(availabilityFor($this->main)['open'])->toBeTrue();
    });

    it('treats a session not seen for over 5 minutes as closed', function () {
        seenAgo($this->main, 301);

        expect(availabilityFor($this->main))->toMatchArray(['open' => false, 'staff_count' => 0]);
    });

    it('ignores closed sessions', function () {
        seenAgo($this->main, 5, ['closed_at' => now(), 'close_reason' => CloseReason::Manual]);

        expect(availabilityFor($this->main)['open'])->toBeFalse();
    });

    it('counts distinct staff members', function () {
        $staff = User::factory()->staff()->create();
        seenAgo($this->main, 5, ['user_id' => $staff->id]);
        seenAgo($this->main, 5, ['user_id' => $staff->id]);
        seenAgo($this->main, 5);

        expect(availabilityFor($this->main)['staff_count'])->toBe(2);
    });

    it('answers isOpen for a single location', function () {
        seenAgo($this->ccs, 30);

        $service = app(AvailabilityService::class);

        expect($service->isOpen($this->ccs))->toBeTrue()
            ->and($service->isOpen($this->main))->toBeFalse();
    });
});

describe('CloseIdleStations', function () {
    it('closes sessions idle for more than 5 minutes with reason timeout', function () {
        $idle = seenAgo($this->main, 301);
        $fresh = seenAgo($this->main, 60);
        $manual = seenAgo($this->ccs, 900, ['closed_at' => now()->subMinutes(10), 'close_reason' => CloseReason::Manual]);

        $this->artisan('stations:close-idle')
            ->expectsOutputToContain('Closed 1 idle station session')
            ->assertSuccessful();

        expect($idle->fresh()->closed_at)->not->toBeNull()
            ->and($idle->fresh()->close_reason)->toBe(CloseReason::Timeout)
            ->and($fresh->fresh()->closed_at)->toBeNull()
            ->and($manual->fresh()->close_reason)->toBe(CloseReason::Manual);
    });

    it('is scheduled every minute', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'stations:close-idle'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('* * * * *');
    });
});
