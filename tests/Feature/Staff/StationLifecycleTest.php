<?php

use App\Enums\CloseReason;
use App\Models\Location;
use App\Models\StationSession;
use App\Models\User;

beforeEach(function () {
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
    $this->staff = User::factory()->staff()->create();
});

function openStation(User $user, Location $location, array $attributes = []): StationSession
{
    return StationSession::factory()->for($user)->for($location)->create($attributes);
}

describe('heartbeat', function () {
    it('updates last_seen_at of the open session', function () {
        $session = openStation($this->staff, $this->main, ['last_seen_at' => now()->subMinutes(2)]);
        $this->freezeSecond();

        $this->actingAs($this->staff)
            ->postJson(route('api.heartbeat'))
            ->assertOk()
            ->assertJson(['clocked_in' => true, 'location' => ['id' => $this->main->id, 'name' => 'Main IT Printing Room']]);

        expect($session->fresh()->last_seen_at->equalTo(now()))->toBeTrue();
    });

    it('reports when the user is not clocked in', function () {
        openStation($this->staff, $this->main, ['closed_at' => now(), 'close_reason' => CloseReason::Timeout]);

        $this->actingAs($this->staff)
            ->postJson(route('api.heartbeat'))
            ->assertOk()
            ->assertJson(['clocked_in' => false, 'location' => null]);
    });

    it('does not touch other users\' sessions', function () {
        $other = openStation(User::factory()->staff()->create(), $this->main, ['last_seen_at' => now()->subMinutes(3)]);
        $before = $other->last_seen_at;

        $this->actingAs($this->staff)->postJson(route('api.heartbeat'));

        expect($other->fresh()->last_seen_at->equalTo($before))->toBeTrue();
    });

    it('is only for staff and admins', function () {
        $this->postJson(route('api.heartbeat'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('api.heartbeat'))->assertForbidden();
    });

    it('is wired into staff pages every 30 seconds', function () {
        $this->actingAs($this->staff)
            ->get(route('staff.station'))
            ->assertSee('name="eprint-heartbeat"', false)
            ->assertSee(route('api.heartbeat'), false);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertDontSee('name="eprint-heartbeat"', false);
    });
});

describe('clock out', function () {
    it('closes the open session with reason manual', function () {
        $session = openStation($this->staff, $this->main);

        $this->actingAs($this->staff)
            ->post(route('staff.station.clock-out'))
            ->assertRedirect(route('staff.station'))
            ->assertSessionHas('success');

        $session->refresh();
        expect($session->closed_at)->not->toBeNull()
            ->and($session->close_reason)->toBe(CloseReason::Manual);
    });

    it('closes every open session the user has', function () {
        $a = openStation($this->staff, $this->main);
        $b = openStation($this->staff, $this->ccs);

        $this->actingAs($this->staff)->post(route('staff.station.clock-out'));

        expect(StationSession::whereNull('closed_at')->count())->toBe(0);
    });

    it('keeps already closed sessions unchanged', function () {
        $old = openStation($this->staff, $this->main, ['closed_at' => now()->subDay(), 'close_reason' => CloseReason::Timeout]);

        $this->actingAs($this->staff)->post(route('staff.station.clock-out'));

        expect($old->fresh()->close_reason)->toBe(CloseReason::Timeout);
    });

    it('shows clock out and switch controls when clocked in', function () {
        openStation($this->staff, $this->main);

        $this->actingAs($this->staff)
            ->get(route('staff.station'))
            ->assertSee('Clock Out')
            ->assertSee('Switch to CCS Office');
    });
});

describe('switch location', function () {
    it('closes the current session with reason switch and opens a new one', function () {
        $old = openStation($this->staff, $this->main);

        $this->actingAs($this->staff)
            ->post(route('staff.station.switch'), ['location_id' => $this->ccs->id])
            ->assertRedirect(route('staff.station'));

        expect($old->fresh()->close_reason)->toBe(CloseReason::Switch)
            ->and($old->fresh()->closed_at)->not->toBeNull();

        $new = StationSession::whereNull('closed_at')->sole();
        expect($new->location_id)->toBe($this->ccs->id)
            ->and($new->user_id)->toBe($this->staff->id);
    });

    it('requires a valid location', function () {
        openStation($this->staff, $this->main);

        $this->actingAs($this->staff)
            ->post(route('staff.station.switch'), ['location_id' => 9999])
            ->assertSessionHasErrors('location_id');

        expect(StationSession::whereNull('closed_at')->sole()->location_id)->toBe($this->main->id);
    });
});

describe('logout', function () {
    it('closes the open session with reason logout', function () {
        $session = openStation($this->staff, $this->main);

        $this->actingAs($this->staff)->post('/logout');

        expect($session->fresh()->close_reason)->toBe(CloseReason::Logout)
            ->and($session->fresh()->closed_at)->not->toBeNull();
    });

    it('works for users without a station', function () {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    });
});

test('clocking in never leaves two open sessions', function () {
    openStation($this->staff, $this->main);

    $this->actingAs($this->staff)->post(route('staff.station.store'), ['location_id' => $this->ccs->id]);

    expect(StationSession::whereNull('closed_at')->count())->toBe(1);
});
