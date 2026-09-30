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
    $this->location = Location::factory()->create(['name' => 'CCS Office']);
    $this->passport = Service::factory()->photo()->create(['name' => 'Passport', 'price' => '45.00', 'pieces_per_set' => 4]);
});

function photoData(array $overrides = []): array
{
    return array_merge([
        'location_id' => test()->location->id,
        'service_id' => test()->passport->id,
        'file' => UploadedFile::fake()->image('me.jpg', 600, 600),
        'sets' => 2,
        'notes' => 'White background',
    ], $overrides);
}

test('the photo form is for students and lists active photo services only', function () {
    Service::factory()->create(['name' => 'Short Black & White']);
    Service::factory()->photo()->inactive()->create(['name' => 'Old 3x3']);

    $this->actingAs(User::factory()->staff()->create())->get(route('student.photos.create'))->assertForbidden();

    $this->actingAs($this->student)
        ->get(route('student.photos.create'))
        ->assertOk()
        ->assertSee('Passport')
        ->assertSee('CCS Office')
        ->assertDontSee('Short Black &amp; White', false)
        ->assertDontSee('Old 3x3')
        ->assertSee('name="sets"', false);
});

test('students submit a photo job priced by sets', function () {
    $this->actingAs($this->student)
        ->post(route('student.photos.store'), photoData())
        ->assertRedirect()
        ->assertSessionHas('success');

    $job = PrintJob::sole();
    expect($job->service_id)->toBe($this->passport->id)
        ->and($job->status)->toBe(JobStatus::Pending)
        ->and($job->sets)->toBe(2)
        ->and($job->copies)->toBe(1)
        ->and($job->page_count)->toBe(1)
        ->and($job->page_range)->toBeNull()
        ->and($job->duplex)->toBeFalse()
        ->and($job->file_type)->toBe('jpg')
        ->and($job->unit_price)->toBe('45.00')
        ->and($job->estimated_total)->toBe('90.00')
        ->and($job->notes)->toBe('White background')
        ->and($job->claim_code)->toMatch('/^EP-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}$/');

    Storage::disk('local')->assertExists($job->stored_path);
    expect($job->stored_path)->toStartWith('uploads/')
        ->and(JobLog::sole()->to_status)->toBe(JobStatus::Pending);
});

test('photo jobs accept PNG too', function () {
    $this->actingAs($this->student)
        ->post(route('student.photos.store'), photoData(['file' => UploadedFile::fake()->image('me.png')]))
        ->assertSessionHasNoErrors();

    expect(PrintJob::sole()->file_type)->toBe('png');
});

test('photo jobs only accept images', function (Closure $file) {
    $this->actingAs($this->student)
        ->post(route('student.photos.store'), photoData(['file' => $file()]))
        ->assertSessionHasErrors('file');

    expect(PrintJob::count())->toBe(0);
})->with([
    'pdf' => [fn () => new UploadedFile(base_path('tests/Fixtures/one-page.pdf'), 'me.pdf', 'application/pdf', null, true)],
    'docx' => [fn () => new UploadedFile(base_path('tests/Fixtures/sample.docx'), 'me.docx', null, null, true)],
    'missing' => [fn () => null],
    'over 20 MB' => [fn () => UploadedFile::fake()->image('big.jpg')->size(20481)],
]);

test('photo options are validated', function (array $overrides, string $field) {
    $this->actingAs($this->student)
        ->post(route('student.photos.store'), photoData($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'sets 0' => [['sets' => 0], 'sets'],
    'sets over 100' => [['sets' => 101], 'sets'],
    'no location' => [['location_id' => null], 'location_id'],
    'notes too long' => [['notes' => str_repeat('x', 501)], 'notes'],
]);

test('only active photo services are accepted', function () {
    $document = Service::factory()->create();
    $inactive = Service::factory()->photo()->inactive()->create();

    $this->actingAs($this->student)->post(route('student.photos.store'), photoData(['service_id' => $document->id]))
        ->assertSessionHasErrors('service_id');
    $this->actingAs($this->student)->post(route('student.photos.store'), photoData(['service_id' => $inactive->id]))
        ->assertSessionHasErrors('service_id');
});

test('a closed location shows the waiting note', function () {
    $this->actingAs($this->student)
        ->post(route('student.photos.store'), photoData())
        ->assertSessionHas('warning', 'CCS Office is closed. Your job waits in the queue until staff open the station.');
});
