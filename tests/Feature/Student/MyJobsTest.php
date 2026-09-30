<?php

use App\Enums\JobStatus;
use App\Models\JobLog;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->student = User::factory()->create();
    $this->other = User::factory()->create();
    $this->location = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
    $this->bw = Service::factory()->create(['name' => 'Short Black & White', 'price' => '2.00']);
    $this->color = Service::factory()->create(['name' => 'Short Colored', 'price' => '5.00']);
});

function ownJob(array $attributes = []): PrintJob
{
    Storage::disk('local')->put('uploads/abc123.pdf', 'pdf');

    return PrintJob::factory()->create(array_merge([
        'user_id' => test()->student->id,
        'location_id' => test()->location->id,
        'service_id' => test()->bw->id,
        'stored_path' => 'uploads/abc123.pdf',
        'file_type' => 'pdf',
        'page_count' => 10,
        'page_range' => null,
        'copies' => 1,
        'unit_price' => '2.00',
        'estimated_total' => '20.00',
        'claim_code' => 'EP-7KQ2',
    ], $attributes));
}

describe('My Jobs', function () {
    it('lists only the student\'s own jobs, newest first', function () {
        ownJob(['claim_code' => 'EP-AAAA', 'created_at' => now()->subDay()]);
        PrintJob::factory()->create(['user_id' => $this->student->id, 'claim_code' => 'EP-BBBB']);
        PrintJob::factory()->create(['user_id' => $this->other->id, 'claim_code' => 'EP-CCCC']);

        $this->actingAs($this->student)
            ->get(route('student.jobs.index'))
            ->assertOk()
            ->assertSeeInOrder(['EP-BBBB', 'EP-AAAA'])
            ->assertDontSee('EP-CCCC');
    });
});

describe('Job details', function () {
    it('shows the claim code, QR, status and price', function () {
        $job = ownJob();

        $this->actingAs($this->student)
            ->get(route('student.jobs.show', $job))
            ->assertOk()
            ->assertSee('EP-7KQ2')
            ->assertSee('<svg', false)
            ->assertSee('Pending')
            ->assertSee('20.00')
            ->assertSee('Main IT Printing Room');
    });

    it('is private to the owner', function () {
        $job = ownJob();

        $this->actingAs($this->other)->get(route('student.jobs.show', $job))->assertForbidden();
    });

    it('shows the status history', function () {
        $job = ownJob();
        JobLog::factory()->for($job)->create(['from_status' => null, 'to_status' => JobStatus::Pending, 'note' => 'Submitted']);

        $this->actingAs($this->student)->get(route('student.jobs.show', $job))->assertSee('Submitted');
    });

    it('is where students land after submitting', function () {
        $this->actingAs($this->student)->post(route('student.jobs.store'), [
            'location_id' => $this->location->id,
            'service_id' => $this->bw->id,
            'file' => new UploadedFile(base_path('tests/Fixtures/three-pages.pdf'), 'a.pdf', 'application/pdf', null, true),
            'copies' => 1,
        ])->assertRedirect(route('student.jobs.show', PrintJob::sole()));
    });
});

describe('edit while pending', function () {
    it('updates options and recalculates the estimate', function () {
        $job = ownJob();

        $this->actingAs($this->student)->get(route('student.jobs.edit', $job))->assertOk();

        $this->actingAs($this->student)
            ->put(route('student.jobs.update', $job), [
                'location_id' => $this->ccs->id,
                'service_id' => $this->color->id,
                'copies' => 2,
                'page_range' => '1-3',
                'duplex' => '1',
                'notes' => 'Updated',
            ])
            ->assertRedirect(route('student.jobs.show', $job))
            ->assertSessionHas('success');

        $job->refresh();
        expect($job->location_id)->toBe($this->ccs->id)
            ->and($job->service_id)->toBe($this->color->id)
            ->and($job->copies)->toBe(2)
            ->and($job->page_range)->toBe('1-3')
            ->and($job->duplex)->toBeTrue()
            ->and($job->notes)->toBe('Updated')
            ->and($job->unit_price)->toBe('5.00')
            ->and($job->estimated_total)->toBe('30.00')
            ->and($job->claim_code)->toBe('EP-7KQ2')
            ->and($job->status)->toBe(JobStatus::Pending);
    });

    it('rejects a range beyond the document', function () {
        $job = ownJob();

        $this->actingAs($this->student)
            ->put(route('student.jobs.update', $job), ['location_id' => $this->location->id, 'service_id' => $this->bw->id, 'copies' => 1, 'page_range' => '5-11'])
            ->assertSessionHasErrors('page_range');

        expect($job->fresh()->estimated_total)->toBe('20.00');
    });

    it('keeps photo jobs on photo services', function () {
        $passport = Service::factory()->photo()->create(['price' => '45.00']);
        $twoByTwo = Service::factory()->photo()->create(['price' => '30.00']);
        $job = ownJob(['service_id' => $passport->id, 'file_type' => 'jpg', 'page_count' => 1, 'sets' => 1, 'unit_price' => '45.00', 'estimated_total' => '45.00']);

        $this->actingAs($this->student)
            ->put(route('student.jobs.update', $job), ['location_id' => $this->location->id, 'service_id' => $this->bw->id, 'sets' => 2])
            ->assertSessionHasErrors('service_id');

        $this->actingAs($this->student)
            ->put(route('student.jobs.update', $job), ['location_id' => $this->location->id, 'service_id' => $twoByTwo->id, 'sets' => 3])
            ->assertSessionHasNoErrors();

        expect($job->fresh()->estimated_total)->toBe('90.00')
            ->and($job->fresh()->sets)->toBe(3);
    });

    it('is blocked once the job leaves pending', function (JobStatus $status) {
        $job = ownJob(['status' => $status]);

        $this->actingAs($this->student)->get(route('student.jobs.edit', $job))->assertForbidden();
        $this->actingAs($this->student)
            ->put(route('student.jobs.update', $job), ['location_id' => $this->ccs->id, 'service_id' => $this->bw->id, 'copies' => 5])
            ->assertForbidden();

        expect($job->fresh()->copies)->toBe(1);
    })->with([JobStatus::Accepted, JobStatus::Printing, JobStatus::Ready, JobStatus::Claimed, JobStatus::Rejected, JobStatus::Cancelled]);

    it('is blocked for other students', function () {
        $job = ownJob();

        $this->actingAs($this->other)
            ->put(route('student.jobs.update', $job), ['location_id' => $this->ccs->id, 'service_id' => $this->bw->id, 'copies' => 5])
            ->assertForbidden();
    });
});

describe('delete while pending', function () {
    it('removes the job and its files', function () {
        $job = ownJob(['pdf_path' => 'uploads/abc123-converted.pdf']);
        Storage::disk('local')->put('uploads/abc123-converted.pdf', 'pdf');

        $this->actingAs($this->student)
            ->delete(route('student.jobs.destroy', $job))
            ->assertRedirect(route('student.jobs.index'))
            ->assertSessionHas('success');

        expect(PrintJob::count())->toBe(0);
        Storage::disk('local')->assertMissing('uploads/abc123.pdf');
        Storage::disk('local')->assertMissing('uploads/abc123-converted.pdf');
    });

    it('is blocked once the job leaves pending or for other students', function () {
        $accepted = ownJob(['status' => JobStatus::Accepted]);

        $this->actingAs($this->student)->delete(route('student.jobs.destroy', $accepted))->assertForbidden();
        Storage::disk('local')->assertExists('uploads/abc123.pdf');

        $pending = PrintJob::factory()->create(['user_id' => $this->student->id]);
        $this->actingAs($this->other)->delete(route('student.jobs.destroy', $pending))->assertForbidden();

        expect(PrintJob::count())->toBe(2);
    });
});
