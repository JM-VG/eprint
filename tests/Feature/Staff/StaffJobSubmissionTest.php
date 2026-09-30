<?php

use App\Enums\JobStatus;
use App\Enums\SaleSource;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Service;
use App\Models\StationSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->location = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->service = Service::factory()->create(['name' => 'Long Black & White', 'price' => '3.00']);
});

function staffJobData(array $overrides = []): array
{
    return array_merge([
        'location_id' => test()->location->id,
        'service_id' => test()->service->id,
        'file' => new UploadedFile(base_path('tests/Fixtures/three-pages.pdf'), 'memo.pdf', 'application/pdf', null, true),
        'copies' => 1,
    ], $overrides);
}

test('staff and admins see the same job form', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('staff.jobs.create'))
        ->assertOk()
        ->assertSee('Long Black &amp; White', false)
        ->assertSee('action="'.route('staff.jobs.store').'"', false)
        ->assertSee('data-estimate-url="'.route('api.price-estimate').'"', false);
})->with(['staff', 'admin']);

test('staff jobs carry source staff_job and belong to the submitter', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->post(route('staff.jobs.store'), staffJobData())
        ->assertRedirect(route('staff.jobs.create'))
        ->assertSessionHas('success');

    $job = PrintJob::sole();
    expect($job->source)->toBe(SaleSource::StaffJob)
        ->and($job->user_id)->toBe($user->id)
        ->and($job->status)->toBe(JobStatus::Pending)
        ->and($job->page_count)->toBe(3)
        ->and($job->estimated_total)->toBe('9.00');
    expect(session('success'))->toContain($job->claim_code);
    Storage::disk('local')->assertExists($job->stored_path);
})->with(['staff', 'admin']);

test('submitting a job does not need an open station', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('staff.jobs.store'), staffJobData())
        ->assertSessionHasNoErrors();

    expect(PrintJob::count())->toBe(1);
});

test('staff jobs show up in the queue', function () {
    $staff = User::factory()->staff()->create(['name' => 'Maria Santos']);
    StationSession::factory()->for($staff)->for($this->location)->create(['last_seen_at' => now()]);

    $this->actingAs($staff)->post(route('staff.jobs.store'), staffJobData());

    $this->actingAs($staff)
        ->get(route('staff.queue'))
        ->assertSee(PrintJob::sole()->claim_code)
        ->assertSee('Maria Santos');
});

test('the same validation applies', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('staff.jobs.store'), staffJobData(['file' => UploadedFile::fake()->create('x.exe', 5, 'application/x-msdownload')]))
        ->assertSessionHasErrors('file');

    expect(PrintJob::count())->toBe(0);
});

test('students cannot use the staff form or mark jobs as staff jobs', function () {
    $student = User::factory()->create();

    $this->actingAs($student)->get(route('staff.jobs.create'))->assertForbidden();
    $this->actingAs($student)->post(route('staff.jobs.store'), staffJobData())->assertForbidden();

    $this->actingAs($student)->post(route('student.jobs.store'), staffJobData(['source' => 'staff_job']));
    expect(PrintJob::sole()->source)->toBe(SaleSource::Online);
});
