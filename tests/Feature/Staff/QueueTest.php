<?php

use App\Enums\JobStatus;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\StationSession;
use App\Models\User;

beforeEach(function () {
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
    $this->staff = User::factory()->staff()->create();
    StationSession::factory()->for($this->staff)->for($this->main)->create(['last_seen_at' => now()]);
});

function queued(Location $location, string $code, array $attributes = []): PrintJob
{
    return PrintJob::factory()->for($location)->create(array_merge(['claim_code' => $code], $attributes));
}

test('the queue needs an open station', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('staff.queue'))->assertRedirect(route('staff.station'));
});

test('students cannot see the queue', function () {
    $this->actingAs(User::factory()->create())->get(route('staff.queue'))->assertForbidden();
});

test('it shows active jobs at the current station, oldest first', function () {
    queued($this->main, 'EP-NEWR', ['created_at' => now()->subMinutes(5)]);
    queued($this->main, 'EP-OLDR', ['created_at' => now()->subHour()]);
    queued($this->main, 'EP-PRNT', ['status' => JobStatus::Printing, 'created_at' => now()->subMinutes(30)]);
    queued($this->ccs, 'EP-CCSX');

    $this->actingAs($this->staff)
        ->get(route('staff.queue'))
        ->assertOk()
        ->assertSeeInOrder(['EP-OLDR', 'EP-PRNT', 'EP-NEWR'])
        ->assertDontSee('EP-CCSX');
});

test('finished jobs are not in the queue', function (JobStatus $status) {
    queued($this->main, 'EP-DONE', ['status' => $status]);

    $this->actingAs($this->staff)->get(route('staff.queue'))->assertDontSee('EP-DONE');
})->with([JobStatus::Claimed, JobStatus::Rejected, JobStatus::Cancelled]);

test('the other location has its own tab', function () {
    queued($this->main, 'EP-MAIN');
    queued($this->ccs, 'EP-CCSX');

    $this->actingAs($this->staff)
        ->get(route('staff.queue', ['location' => $this->ccs->id]))
        ->assertOk()
        ->assertSee('EP-CCSX')
        ->assertDontSee('EP-MAIN');
});

test('tabs show each location with its pending count, current location first', function () {
    queued($this->main, 'EP-A001');
    queued($this->ccs, 'EP-B001');
    queued($this->ccs, 'EP-B002');
    queued($this->ccs, 'EP-B003', ['status' => JobStatus::Accepted]);

    $this->actingAs($this->staff)
        ->get(route('staff.queue'))
        ->assertSeeInOrder(['Main IT Printing Room', '1', 'CCS Office', '2']);
});

test('an unknown location tab is a 404', function () {
    $this->actingAs($this->staff)->get(route('staff.queue', ['location' => 999]))->assertNotFound();
});

test('rows link to the job file', function () {
    $job = queued($this->main, 'EP-FILE');

    $this->actingAs($this->staff)
        ->get(route('staff.queue'))
        ->assertSee(route('files.jobs.show', $job), false);
});

test('admins with an open station can use the queue', function () {
    $admin = User::factory()->admin()->create();
    StationSession::factory()->for($admin)->for($this->ccs)->create(['last_seen_at' => now()]);
    queued($this->ccs, 'EP-ADMN');

    $this->actingAs($admin)->get(route('staff.queue'))->assertOk()->assertSee('EP-ADMN');
});

describe('badge count endpoint', function () {
    it('returns pending counts per location and the current location', function () {
        queued($this->main, 'EP-A001');
        queued($this->main, 'EP-A002');
        queued($this->main, 'EP-A003', ['status' => JobStatus::Ready]);
        queued($this->ccs, 'EP-B001');

        $this->actingAs($this->staff)
            ->getJson(route('api.queue-count'))
            ->assertOk()
            ->assertExactJson([
                'location_id' => $this->main->id,
                'pending' => 2,
                'counts' => [(string) $this->main->id => 2, (string) $this->ccs->id => 1],
            ]);
    });

    it('works without a station and is staff only', function () {
        $this->actingAs(User::factory()->staff()->create())
            ->getJson(route('api.queue-count'))
            ->assertOk()
            ->assertJson(['location_id' => null, 'pending' => 0]);

        $this->actingAs(User::factory()->create())->getJson(route('api.queue-count'))->assertForbidden();
    });

    it('is polled every 15 seconds from the sidebar badge', function () {
        $this->actingAs($this->staff)
            ->get(route('staff.station'))
            ->assertSee('data-queue-badge', false)
            ->assertSee('data-endpoint="'.route('api.queue-count').'"', false);

        expect(file_get_contents(resource_path('js/queue-badge.js')))->toContain('15_000')
            ->and(file_get_contents(resource_path('js/app.js')))->toContain('startQueueBadge');
    });
});
