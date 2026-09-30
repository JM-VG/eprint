<?php

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/f1a2b3.pdf', '%PDF-1.4 owner file');

    $this->owner = User::factory()->create();
    $this->job = PrintJob::factory()->create([
        'user_id' => $this->owner->id,
        'stored_path' => 'uploads/f1a2b3.pdf',
        'original_name' => 'My Thesis.pdf',
        'file_type' => 'pdf',
    ]);
});

test('the owner can open their file', function () {
    $response = $this->actingAs($this->owner)->get(route('files.jobs.show', $this->job));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Disposition'))->toContain('inline')->toContain('My Thesis.pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->streamedContent())->toBe('%PDF-1.4 owner file');
});

test('staff and admins can open any job file', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('files.jobs.show', $this->job))
        ->assertOk();
})->with(['staff', 'admin']);

test('other students cannot open the file', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('files.jobs.show', $this->job))
        ->assertForbidden();
});

test('guests are sent to login', function () {
    $this->get(route('files.jobs.show', $this->job))->assertRedirect(route('login'));
});

test('disabled accounts lose access', function () {
    $this->actingAs(User::factory()->staff()->disabled()->create())
        ->get(route('files.jobs.show', $this->job))
        ->assertRedirect(route('login'));
});

test('staff can download the file as an attachment', function () {
    $response = $this->actingAs(User::factory()->staff()->create())
        ->get(route('files.jobs.show', ['job' => $this->job, 'download' => 1]));

    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('the converted PDF of a DOCX job can be opened', function () {
    Storage::disk('local')->put('uploads/doc9.docx', 'docx bytes');
    Storage::disk('local')->put('uploads/doc9.pdf', '%PDF-1.4 converted');
    $job = PrintJob::factory()->create([
        'user_id' => $this->owner->id,
        'stored_path' => 'uploads/doc9.docx',
        'pdf_path' => 'uploads/doc9.pdf',
        'original_name' => 'Report.docx',
        'file_type' => 'docx',
    ]);

    $response = $this->actingAs($this->owner)->get(route('files.jobs.show', ['job' => $job, 'variant' => 'pdf']));

    $response->assertOk();
    expect($response->streamedContent())->toBe('%PDF-1.4 converted')
        ->and($response->headers->get('Content-Disposition'))->toContain('Report.pdf');
});

test('asking for a converted PDF that does not exist is a 404', function () {
    $this->actingAs($this->owner)
        ->get(route('files.jobs.show', ['job' => $this->job, 'variant' => 'pdf']))
        ->assertNotFound();
});

test('files removed by retention cleanup return 410 Gone', function () {
    $this->job->forceFill(['files_deleted_at' => now()])->save();

    $this->actingAs($this->owner)->get(route('files.jobs.show', $this->job))->assertStatus(410);
});

test('a missing file on disk is a 404', function () {
    Storage::disk('local')->delete('uploads/f1a2b3.pdf');

    $this->actingAs($this->owner)->get(route('files.jobs.show', $this->job))->assertNotFound();
});

test('uploads are not reachable through the storage URL or public disk', function () {
    expect(config('filesystems.disks.local.serve'))->toBeFalse();

    $this->get('/storage/uploads/f1a2b3.pdf')->assertNotFound();
});

test('the job page links to the file', function () {
    $this->actingAs($this->owner)
        ->get(route('student.jobs.show', $this->job))
        ->assertSee(route('files.jobs.show', $this->job), false);
});
