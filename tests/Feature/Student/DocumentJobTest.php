<?php

use App\Enums\JobStatus;
use App\Enums\PaymentStatus;
use App\Enums\SaleSource;
use App\Models\JobLog;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\Service;
use App\Models\StationSession;
use App\Models\User;
use App\Services\ClaimCodeGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    config(['eprint.docx_conversion' => false]);

    $this->student = User::factory()->create();
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office']);
    $this->bw = Service::factory()->create(['name' => 'Short Black & White', 'price' => '2.00']);
});

function pdfUpload(string $fixture = 'three-pages.pdf', string $name = 'thesis.pdf'): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/'.$fixture), $name, 'application/pdf', null, true);
}

function jobData(array $overrides = []): array
{
    return array_merge([
        'location_id' => test()->main->id,
        'service_id' => test()->bw->id,
        'file' => pdfUpload(),
        'copies' => 2,
        'page_range' => '',
        'duplex' => '0',
        'notes' => 'Please staple',
    ], $overrides);
}

describe('form', function () {
    it('is for students only', function (string $state) {
        $this->actingAs(User::factory()->{$state}()->create())
            ->get(route('student.jobs.create'))
            ->assertForbidden();
    })->with(['staff', 'admin']);

    it('lists locations and active document services only', function () {
        Service::factory()->inactive()->create(['name' => 'Retired Service']);
        Service::factory()->photo()->create(['name' => '2x2']);

        $this->actingAs($this->student)
            ->get(route('student.jobs.create'))
            ->assertOk()
            ->assertSee('Main IT Printing Room')
            ->assertSee('CCS Office')
            ->assertSee('Short Black &amp; White', false)
            ->assertDontSee('Retired Service')
            ->assertDontSee('2x2')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('data-estimate-url="'.route('api.price-estimate').'"', false);
    });
});

describe('submit', function () {
    it('creates a pending job with a private file, page count and price snapshot', function () {
        $response = $this->actingAs($this->student)->post(route('student.jobs.store'), jobData());

        $job = PrintJob::sole();
        $response->assertRedirect()->assertSessionHas('success');
        expect(session('success'))->toContain($job->claim_code);

        expect($job->user_id)->toBe($this->student->id)
            ->and($job->location_id)->toBe($this->main->id)
            ->and($job->service_id)->toBe($this->bw->id)
            ->and($job->source)->toBe(SaleSource::Online)
            ->and($job->status)->toBe(JobStatus::Pending)
            ->and($job->payment_status)->toBe(PaymentStatus::Unpaid)
            ->and($job->original_name)->toBe('thesis.pdf')
            ->and($job->file_type)->toBe('pdf')
            ->and($job->file_size)->toBeGreaterThan(0)
            ->and($job->page_count)->toBe(3)
            ->and($job->copies)->toBe(2)
            ->and($job->duplex)->toBeFalse()
            ->and($job->notes)->toBe('Please staple')
            ->and($job->unit_price)->toBe('2.00')
            ->and($job->estimated_total)->toBe('12.00')
            ->and($job->final_total)->toBeNull()
            ->and($job->claim_code)->toMatch('/^EP-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}$/');

        expect($job->stored_path)->toStartWith('uploads/')
            ->and($job->stored_path)->not->toContain('thesis');
        Storage::disk('local')->assertExists($job->stored_path);
        Storage::disk('public')->assertMissing($job->stored_path);
    });

    it('writes a job log for the new job', function () {
        $this->actingAs($this->student)->post(route('student.jobs.store'), jobData());

        $log = JobLog::sole();
        expect($log->from_status)->toBeNull()
            ->and($log->to_status)->toBe(JobStatus::Pending)
            ->and($log->user_id)->toBe($this->student->id);
    });

    it('prices only the pages in the range', function () {
        $this->actingAs($this->student)->post(route('student.jobs.store'), jobData(['page_range' => '2-3', 'copies' => 1]));

        expect(PrintJob::sole()->estimated_total)->toBe('4.00')
            ->and(PrintJob::sole()->page_range)->toBe('2-3');
    });

    it('rejects malformed ranges and ranges beyond the document', function (string $range) {
        $this->actingAs($this->student)
            ->post(route('student.jobs.store'), jobData(['page_range' => $range]))
            ->assertSessionHasErrors('page_range');

        expect(PrintJob::count())->toBe(0);
        expect(Storage::disk('local')->allFiles())->toBe([]);
    })->with(['abc', '3-1', '2-9']);

    it('keeps DOCX page count and total empty when conversion is off', function () {
        $docx = new UploadedFile(base_path('tests/Fixtures/sample.docx'), 'report.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $this->actingAs($this->student)->post(route('student.jobs.store'), jobData(['file' => $docx]))
            ->assertSessionHasNoErrors();

        $job = PrintJob::sole();
        expect($job->file_type)->toBe('docx')
            ->and($job->page_count)->toBeNull()
            ->and($job->estimated_total)->toBeNull()
            ->and($job->unit_price)->toBe('2.00');
    });

    it('counts images as one page', function () {
        $this->actingAs($this->student)->post(route('student.jobs.store'), jobData([
            'file' => UploadedFile::fake()->image('poster.png', 800, 600),
            'copies' => 3,
        ]))->assertSessionHasNoErrors();

        expect(PrintJob::sole()->page_count)->toBe(1)
            ->and(PrintJob::sole()->estimated_total)->toBe('6.00');
    });

    it('validates the upload', function (Closure $file, string $field) {
        $this->actingAs($this->student)
            ->post(route('student.jobs.store'), jobData(['file' => $file()]))
            ->assertSessionHasErrors($field);

        expect(PrintJob::count())->toBe(0);
    })->with([
        'missing' => [fn () => null, 'file'],
        'wrong type' => [fn () => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'), 'file'],
        'text file' => [fn () => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'), 'file'],
        'over 20 MB' => [fn () => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf'), 'file'],
    ]);

    it('rejects an unreadable PDF and keeps no file', function () {
        $this->actingAs($this->student)
            ->post(route('student.jobs.store'), jobData(['file' => pdfUpload('corrupt.pdf', 'broken.pdf')]))
            ->assertSessionHasErrors('file');

        expect(PrintJob::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('validates the options', function (array $overrides, string $field) {
        $this->actingAs($this->student)
            ->post(route('student.jobs.store'), jobData($overrides))
            ->assertSessionHasErrors($field);
    })->with([
        'no location' => [['location_id' => null], 'location_id'],
        'copies 0' => [['copies' => 0], 'copies'],
        'copies over 100' => [['copies' => 101], 'copies'],
        'notes too long' => [['notes' => str_repeat('x', 501)], 'notes'],
    ]);

    it('only accepts active document services', function () {
        $inactive = Service::factory()->inactive()->create();
        $photo = Service::factory()->photo()->create();

        $this->actingAs($this->student)->post(route('student.jobs.store'), jobData(['service_id' => $inactive->id]))
            ->assertSessionHasErrors('service_id');
        $this->actingAs($this->student)->post(route('student.jobs.store'), jobData(['service_id' => $photo->id]))
            ->assertSessionHasErrors('service_id');
    });

    it('accepts jobs for a closed location with the waiting note', function () {
        $this->actingAs($this->student)
            ->post(route('student.jobs.store'), jobData(['location_id' => $this->ccs->id]))
            ->assertSessionHas('warning', 'CCS Office is closed. Your job waits in the queue until staff open the station.');

        expect(PrintJob::sole()->location_id)->toBe($this->ccs->id);
    });

    it('shows no closed note when staff are on duty', function () {
        StationSession::factory()->for($this->main)->create(['last_seen_at' => now()]);

        $this->actingAs($this->student)
            ->post(route('student.jobs.store'), jobData())
            ->assertSessionMissing('warning');
    });

    it('cannot be used to set status, totals or owner', function () {
        $other = User::factory()->create();

        $this->actingAs($this->student)->post(route('student.jobs.store'), jobData([
            'status' => 'ready', 'final_total' => '0.01', 'user_id' => $other->id,
            'source' => 'staff_job', 'payment_status' => 'paid', 'unit_price' => '0.01',
        ]));

        $job = PrintJob::sole();
        expect($job->status)->toBe(JobStatus::Pending)
            ->and($job->user_id)->toBe($this->student->id)
            ->and($job->final_total)->toBeNull()
            ->and($job->source)->toBe(SaleSource::Online)
            ->and($job->unit_price)->toBe('2.00');
    });
});

describe('price estimate endpoint', function () {
    it('counts the uploaded PDF and returns the estimate', function () {
        $this->actingAs($this->student)
            ->post(route('api.price-estimate'), [
                'service_id' => $this->bw->id, 'file' => pdfUpload(), 'copies' => 2, 'page_range' => '1-2',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['pages' => 2, 'unit_price' => '2.00', 'total' => '8.00']);

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('returns no total for DOCX files', function () {
        $docx = new UploadedFile(base_path('tests/Fixtures/sample.docx'), 'a.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $this->actingAs($this->student)
            ->post(route('api.price-estimate'), ['service_id' => $this->bw->id, 'file' => $docx, 'copies' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['pages' => null, 'total' => null]);
    });

    it('prices photo services by sets', function () {
        $photo = Service::factory()->photo()->create(['price' => '45.00']);

        $this->actingAs($this->student)
            ->postJson(route('api.price-estimate'), ['service_id' => $photo->id, 'sets' => 2])
            ->assertOk()
            ->assertJson(['total' => '90.00']);
    });

    it('validates input and requires login', function () {
        $this->postJson(route('api.price-estimate'), ['service_id' => $this->bw->id])->assertUnauthorized();

        $this->actingAs($this->student)
            ->postJson(route('api.price-estimate'), ['service_id' => 999, 'copies' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id', 'copies']);
    });
});

describe('ClaimCodeGenerator', function () {
    it('generates EP- codes from the allowed alphabet', function () {
        $codes = collect(range(1, 200))->map(fn () => app(ClaimCodeGenerator::class)->generate());

        $codes->each(fn ($code) => expect($code)->toMatch('/^EP-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}$/'));
    });

    it('never returns a code that is already used', function () {
        $taken = PrintJob::factory()->create(['claim_code' => 'EP-AAAA']);
        $generator = new ClaimCodeGenerator(fn () => 'A');

        expect(fn () => $generator->generate())->toThrow(RuntimeException::class);

        $sequence = ['A', 'A', 'A', 'A', 'B', 'B', 'B', 'B'];
        $generator = new ClaimCodeGenerator(function () use (&$sequence) {
            return array_shift($sequence);
        });

        expect($generator->generate())->toBe('EP-BBBB');
    });
});
