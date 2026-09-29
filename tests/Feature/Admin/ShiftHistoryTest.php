<?php

use App\Enums\CloseReason;
use App\Models\Location;
use App\Models\StationSession;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
    $this->maria = User::factory()->staff()->create(['username' => 'maria', 'first_name' => 'Maria', 'last_name' => 'Santos', 'name' => 'Maria Santos']);
    $this->jose = User::factory()->staff()->create(['username' => 'jose', 'first_name' => 'Jose', 'last_name' => 'Reyes', 'name' => 'Jose Reyes']);
});

function shift(User $user, Location $location, string $opened, ?string $closed, ?CloseReason $reason = null): StationSession
{
    return StationSession::factory()->for($user)->for($location)->create([
        'opened_at' => $opened,
        'last_seen_at' => $closed ?? $opened,
        'closed_at' => $closed,
        'close_reason' => $reason,
    ]);
}

test('only admins can view shift history', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('admin.shifts.index'))
        ->assertForbidden();
})->with(['student', 'staff']);

test('admins do not need an open station to view it', function () {
    $this->actingAs($this->admin)->get(route('admin.shifts.index'))->assertOk();
});

test('it shows opened, closed, duration and close reason, newest first', function () {
    shift($this->maria, $this->main, '2026-09-01 08:00:00', '2026-09-01 10:30:00', CloseReason::Manual);
    shift($this->jose, $this->ccs, '2026-09-02 13:00:00', '2026-09-02 13:45:00', CloseReason::Timeout);

    $this->actingAs($this->admin)
        ->get(route('admin.shifts.index'))
        ->assertOk()
        ->assertSeeInOrder(['Jose Reyes', 'CCS Office', 'Sep 2, 2026 1:00 PM', 'Sep 2, 2026 1:45 PM', '45m', 'Timeout'])
        ->assertSeeInOrder(['Maria Santos', 'Main IT Printing Room', 'Sep 1, 2026 8:00 AM', 'Sep 1, 2026 10:30 AM', '2h 30m', 'Manual']);
});

test('open shifts show as ongoing', function () {
    shift($this->maria, $this->main, now()->subMinutes(20)->toDateTimeString(), null);

    $this->actingAs($this->admin)
        ->get(route('admin.shifts.index'))
        ->assertSee('Ongoing');
});

test('it filters by staff member', function () {
    shift($this->maria, $this->main, '2026-09-01 08:00:00', '2026-09-01 09:00:00', CloseReason::Manual);
    shift($this->jose, $this->main, '2026-09-01 08:00:00', '2026-09-01 09:00:00', CloseReason::Manual);

    $this->actingAs($this->admin)
        ->get(route('admin.shifts.index', ['user_id' => $this->jose->id]))
        ->assertSee('Jose Reyes')
        ->assertDontSee('Maria Santos</td>', false);
});

test('it filters by location', function () {
    shift($this->maria, $this->main, '2026-09-01 08:00:00', '2026-09-01 09:00:00', CloseReason::Manual);
    shift($this->jose, $this->ccs, '2026-09-01 08:00:00', '2026-09-01 09:00:00', CloseReason::Manual);

    $response = $this->actingAs($this->admin)->get(route('admin.shifts.index', ['location_id' => $this->ccs->id]));

    expect($response->viewData('shifts')->pluck('user_id')->all())->toBe([$this->jose->id]);
});

test('it filters by an inclusive date range on the opened date', function () {
    $before = shift($this->maria, $this->main, '2026-08-31 23:59:00', '2026-09-01 01:00:00', CloseReason::Manual);
    $first = shift($this->maria, $this->main, '2026-09-01 00:00:00', '2026-09-01 02:00:00', CloseReason::Manual);
    $last = shift($this->maria, $this->main, '2026-09-03 23:59:00', '2026-09-04 01:00:00', CloseReason::Manual);
    $after = shift($this->maria, $this->main, '2026-09-04 00:00:00', '2026-09-04 02:00:00', CloseReason::Manual);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.shifts.index', ['from' => '2026-09-01', 'to' => '2026-09-03']));

    expect($response->viewData('shifts')->pluck('id')->sort()->values()->all())->toBe([$first->id, $last->id]);
});

test('invalid filters are rejected', function (array $query, string $field) {
    $this->actingAs($this->admin)
        ->get(route('admin.shifts.index', $query))
        ->assertSessionHasErrors($field);
})->with([
    [['from' => 'not-a-date'], 'from'],
    [['from' => '2026-09-05', 'to' => '2026-09-01'], 'to'],
    [['location_id' => 999999], 'location_id'],
    [['user_id' => 999999], 'user_id'],
]);

test('the staff filter lists staff and admins only', function () {
    User::factory()->create(['first_name' => 'Student', 'last_name' => 'Person', 'name' => 'Student Person']);

    $this->actingAs($this->admin)
        ->get(route('admin.shifts.index'))
        ->assertSee('Maria Santos')
        ->assertDontSee('Student Person');
});
