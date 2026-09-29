<?php

use App\Models\Location;
use App\Models\StationSession;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room', 'slug' => 'main-it']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office', 'slug' => 'ccs-office']);
    $this->staff = User::factory()->staff()->create();
});

test('staff see the clock in page with both locations', function () {
    $this->actingAs($this->staff)
        ->get(route('staff.station'))
        ->assertOk()
        ->assertSee('Clock In')
        ->assertSee('Main IT Printing Room')
        ->assertSee('CCS Office');
});

test('students cannot open the station page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('staff.station'))
        ->assertForbidden();
});

test('clocking in opens a station session at the chosen location', function () {
    $this->freezeSecond();

    $this->actingAs($this->staff)
        ->post(route('staff.station.store'), ['location_id' => $this->ccs->id])
        ->assertRedirect(route('staff.station'))
        ->assertSessionHas('success');

    $session = StationSession::sole();

    expect($session->user_id)->toBe($this->staff->id)
        ->and($session->location_id)->toBe($this->ccs->id)
        ->and($session->opened_at->equalTo(now()))->toBeTrue()
        ->and($session->last_seen_at->equalTo(now()))->toBeTrue()
        ->and($session->closed_at)->toBeNull()
        ->and($session->close_reason)->toBeNull();
});

test('the station page shows the current location after clocking in', function () {
    $this->actingAs($this->staff)->post(route('staff.station.store'), ['location_id' => $this->ccs->id]);

    $this->actingAs($this->staff)
        ->get(route('staff.station'))
        ->assertSee('You are clocked in at')
        ->assertSee('CCS Office');
});

test('clocking in twice does not open a second session', function () {
    $this->actingAs($this->staff)->post(route('staff.station.store'), ['location_id' => $this->main->id]);
    $this->actingAs($this->staff)
        ->post(route('staff.station.store'), ['location_id' => $this->ccs->id])
        ->assertSessionHas('warning');

    expect(StationSession::whereNull('closed_at')->count())->toBe(1)
        ->and(StationSession::sole()->location_id)->toBe($this->main->id);
});

test('a location is required and must exist', function (mixed $locationId) {
    $this->actingAs($this->staff)
        ->post(route('staff.station.store'), ['location_id' => $locationId])
        ->assertSessionHasErrors('location_id');

    expect(StationSession::count())->toBe(0);
})->with([null, 999999, 'abc']);

test('admins can clock in too', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('staff.station.store'), ['location_id' => $this->main->id]);

    expect(StationSession::sole()->user_id)->toBe($admin->id);
});

test('staff land on the station page after login', function () {
    $staff = User::factory()->staff()->create(['username' => 'counter1']);

    $this->post('/login', ['login' => 'counter1', 'password' => 'password'])
        ->assertRedirect(route('staff.station', absolute: false));
});

describe('EnsureStation', function () {
    beforeEach(function () {
        Route::middleware(['web', 'auth', 'role:staff,admin', 'station'])
            ->get('/_test/queue', fn () => 'station '.request()->attributes->get('station')->location_id);
    });

    it('sends staff without an open station to the clock in page', function () {
        $this->actingAs($this->staff)
            ->get('/_test/queue')
            ->assertRedirect(route('staff.station'))
            ->assertSessionHas('warning');
    });

    it('lets staff with an open station through and exposes the session', function () {
        StationSession::factory()->for($this->staff)->for($this->ccs)->create();

        $this->actingAs($this->staff)
            ->get('/_test/queue')
            ->assertOk()
            ->assertSee('station '.$this->ccs->id);
    });

    it('ignores closed sessions', function () {
        StationSession::factory()->for($this->staff)->for($this->ccs)->closed()->create();

        $this->actingAs($this->staff)->get('/_test/queue')->assertRedirect(route('staff.station'));
    });

    it('ignores other users\' sessions', function () {
        StationSession::factory()->for(User::factory()->staff())->for($this->ccs)->create();

        $this->actingAs($this->staff)->get('/_test/queue')->assertRedirect(route('staff.station'));
    });

    it('applies to admins on job-processing routes', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/_test/queue')
            ->assertRedirect(route('staff.station'));
    });
});
