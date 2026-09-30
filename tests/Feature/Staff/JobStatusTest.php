<?php

use App\Enums\JobStatus;
use App\Exceptions\InvalidJobTransition;
use App\Models\JobLog;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Service;
use App\Models\StationSession;
use App\Models\User;
use App\Services\JobStatusService;

beforeEach(function () {
    $this->location = Location::factory()->create();
    $this->staff = User::factory()->staff()->create();
    StationSession::factory()->for($this->staff)->for($this->location)->create(['last_seen_at' => now()]);
    $this->service = Service::factory()->create(['price' => '2.00']);
    $this->job = PrintJob::factory()->for($this->location)->create([
        'service_id' => $this->service->id,
        'page_count' => 10,
        'copies' => 2,
        'duplex' => false,
        'unit_price' => '2.00',
        'estimated_total' => '40.00',
    ]);
    $this->statuses = app(JobStatusService::class);
});

describe('allowed transitions', function () {
    $allowed = [
        'pending' => ['accepted', 'rejected', 'cancelled'],
        'accepted' => ['printing', 'rejected', 'cancelled'],
        'printing' => ['ready'],
        'ready' => ['claimed'],
        'claimed' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    foreach ($allowed as $from => $targets) {
        foreach (JobStatus::cases() as $to) {
            $ok = in_array($to->value, $targets, true);

            it("{$from} -> {$to->value} is ".($ok ? 'allowed' : 'refused'), function () use ($from, $to, $ok) {
                expect(app(JobStatusService::class)->canTransition(JobStatus::from($from), $to))->toBe($ok);
            });
        }
    }

    it('throws and writes nothing on a refused change', function () {
        expect(fn () => $this->statuses->transition($this->job, JobStatus::Ready, $this->staff))
            ->toThrow(InvalidJobTransition::class);

        expect($this->job->fresh()->status)->toBe(JobStatus::Pending)
            ->and(JobLog::count())->toBe(0);
    });
});

describe('job logs', function () {
    it('writes a row for every change with from, to, actor and note', function () {
        $this->statuses->accept($this->job, $this->staff, pageCount: 10, finalTotal: '40.00');
        $this->statuses->transition($this->job, JobStatus::Printing, $this->staff);
        $this->statuses->transition($this->job, JobStatus::Ready, $this->staff, 'On shelf B');

        $logs = JobLog::orderBy('id')->get();
        expect($logs)->toHaveCount(3)
            ->and($logs->pluck('to_status')->all())->toBe([JobStatus::Accepted, JobStatus::Printing, JobStatus::Ready])
            ->and($logs[0]->from_status)->toBe(JobStatus::Pending)
            ->and($logs[2]->from_status)->toBe(JobStatus::Printing)
            ->and($logs[2]->note)->toBe('On shelf B')
            ->and($logs->pluck('user_id')->unique()->all())->toBe([$this->staff->id]);
    });
});

describe('accept', function () {
    it('stores the final page count, final total and sheets used', function () {
        $this->statuses->accept($this->job, $this->staff, pageCount: 12, finalTotal: '45.50');

        $job = $this->job->fresh();
        expect($job->status)->toBe(JobStatus::Accepted)
            ->and($job->page_count)->toBe(12)
            ->and($job->final_total)->toBe('45.50')
            ->and($job->sheets_used)->toBe(24);
    });

    it('halves sheets for duplex and follows the page range', function () {
        $this->job->forceFill(['duplex' => true, 'page_range' => '1-5', 'copies' => 3])->save();

        $this->statuses->accept($this->job, $this->staff, pageCount: 10, finalTotal: '30.00');

        expect($this->job->fresh()->sheets_used)->toBe(9);
    });

    it('requires a page count and final total', function () {
        expect(fn () => $this->statuses->accept($this->job, $this->staff, pageCount: 0, finalTotal: '10.00'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->statuses->accept($this->job, $this->staff, pageCount: 5, finalTotal: '-1'))
            ->toThrow(InvalidArgumentException::class);

        expect($this->job->fresh()->status)->toBe(JobStatus::Pending);
    });
});

describe('reject', function () {
    it('requires a reason and stores it', function () {
        expect(fn () => $this->statuses->reject($this->job, $this->staff, '  '))
            ->toThrow(InvalidArgumentException::class);

        $this->statuses->reject($this->job, $this->staff, 'File is password protected');

        expect($this->job->fresh()->status)->toBe(JobStatus::Rejected)
            ->and($this->job->fresh()->reject_reason)->toBe('File is password protected')
            ->and(JobLog::sole()->note)->toBe('File is password protected');
    });
});

describe('staff actions from the queue', function () {
    it('accepts with the page count and final total', function () {
        $this->actingAs($this->staff)
            ->post(route('staff.jobs.accept', $this->job), ['page_count' => 10, 'final_total' => '40.00'])
            ->assertRedirect(route('staff.queue'))
            ->assertSessionHas('success');

        expect($this->job->fresh()->status)->toBe(JobStatus::Accepted);
    });

    it('validates accept input', function (array $input, string $field) {
        $this->actingAs($this->staff)
            ->post(route('staff.jobs.accept', $this->job), $input)
            ->assertSessionHasErrors($field);

        expect($this->job->fresh()->status)->toBe(JobStatus::Pending);
    })->with([
        [['final_total' => '40.00'], 'page_count'],
        [['page_count' => 0, 'final_total' => '40.00'], 'page_count'],
        [['page_count' => 10], 'final_total'],
        [['page_count' => 10, 'final_total' => '4.555'], 'final_total'],
    ]);

    it('rejects with a reason', function () {
        $this->actingAs($this->staff)
            ->post(route('staff.jobs.reject', $this->job), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->staff)
            ->post(route('staff.jobs.reject', $this->job), ['reason' => 'Blurry scan'])
            ->assertSessionHas('success');

        expect($this->job->fresh()->status)->toBe(JobStatus::Rejected);
    });

    it('moves accepted jobs to printing and then ready', function () {
        $this->statuses->accept($this->job, $this->staff, 10, '40.00');

        $this->actingAs($this->staff)->post(route('staff.jobs.printing', $this->job))->assertSessionHas('success');
        $this->actingAs($this->staff)->post(route('staff.jobs.ready', $this->job))->assertSessionHas('success');

        expect($this->job->fresh()->status)->toBe(JobStatus::Ready);
    });

    it('turns a refused change into an error message, not a crash', function () {
        $this->actingAs($this->staff)
            ->post(route('staff.jobs.ready', $this->job))
            ->assertRedirect()
            ->assertSessionHas('error');

        expect($this->job->fresh()->status)->toBe(JobStatus::Pending);
    });

    it('needs staff with an open station', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('staff.jobs.accept', $this->job), ['page_count' => 10, 'final_total' => '40.00'])
            ->assertForbidden();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('staff.jobs.accept', $this->job), ['page_count' => 10, 'final_total' => '40.00'])
            ->assertRedirect(route('staff.station'));

        expect($this->job->fresh()->status)->toBe(JobStatus::Pending);
    });

    it('shows accept and reject controls for pending jobs in the queue', function () {
        $this->actingAs($this->staff)
            ->get(route('staff.queue'))
            ->assertSee(route('staff.jobs.accept', $this->job), false)
            ->assertSee(route('staff.jobs.reject', $this->job), false)
            ->assertSee('name="page_count"', false)
            ->assertSee('value="40.00"', false);
    });
});
