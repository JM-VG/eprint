<?php

use App\Enums\JobStatus;
use App\Models\PrintJob;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Setting::create(['key' => 'retention_days', 'value' => '7']);
    $this->freezeSecond();
});

function jobWithFiles(string $name, JobStatus $status, int $ageDays, bool $withPdf = false): PrintJob
{
    Storage::disk('local')->put("uploads/{$name}.docx", 'original');
    if ($withPdf) {
        Storage::disk('local')->put("uploads/{$name}.pdf", 'converted');
    }

    return PrintJob::factory()->create([
        'status' => $status,
        'stored_path' => "uploads/{$name}.docx",
        'pdf_path' => $withPdf ? "uploads/{$name}.pdf" : null,
        'created_at' => now()->subDays($ageDays),
    ]);
}

test('it deletes files of finished jobs older than the retention period and keeps the records', function () {
    $claimed = jobWithFiles('claimed', JobStatus::Claimed, 8, withPdf: true);
    $rejected = jobWithFiles('rejected', JobStatus::Rejected, 30);
    $cancelled = jobWithFiles('cancelled', JobStatus::Cancelled, 10);

    $this->artisan('files:cleanup')
        ->expectsOutputToContain('Removed files for 3 job')
        ->assertSuccessful();

    foreach ([$claimed, $rejected, $cancelled] as $job) {
        expect($job->fresh())->not->toBeNull()
            ->and($job->fresh()->files_deleted_at->equalTo(now()))->toBeTrue();
        Storage::disk('local')->assertMissing($job->stored_path);
    }
    Storage::disk('local')->assertMissing('uploads/claimed.pdf');
});

test('it keeps files that are still within the retention period', function () {
    $recent = jobWithFiles('recent', JobStatus::Claimed, 6);

    $this->artisan('files:cleanup')->assertSuccessful();

    Storage::disk('local')->assertExists('uploads/recent.docx');
    expect($recent->fresh()->files_deleted_at)->toBeNull();
});

test('it never removes files of jobs still in the queue, however old', function (JobStatus $status) {
    $job = jobWithFiles('active', $status, 60);

    $this->artisan('files:cleanup')->assertSuccessful();

    Storage::disk('local')->assertExists('uploads/active.docx');
    expect($job->fresh()->files_deleted_at)->toBeNull();
})->with([JobStatus::Pending, JobStatus::Accepted, JobStatus::Printing, JobStatus::Ready]);

test('it follows the retention_days setting', function () {
    Setting::where('key', 'retention_days')->first()->update(['value' => '30']);
    $job = jobWithFiles('twenty', JobStatus::Claimed, 20);

    $this->artisan('files:cleanup')->assertSuccessful();

    Storage::disk('local')->assertExists('uploads/twenty.docx');
    expect($job->fresh()->files_deleted_at)->toBeNull();
});

test('it skips jobs already cleaned and tolerates missing files', function () {
    $done = PrintJob::factory()->create([
        'status' => JobStatus::Claimed,
        'created_at' => now()->subDays(20),
        'files_deleted_at' => now()->subDays(5),
        'stored_path' => 'uploads/gone.pdf',
    ]);
    $missing = PrintJob::factory()->create([
        'status' => JobStatus::Claimed,
        'created_at' => now()->subDays(20),
        'stored_path' => 'uploads/never-existed.pdf',
    ]);

    $this->artisan('files:cleanup')
        ->expectsOutputToContain('Removed files for 1 job')
        ->assertSuccessful();

    expect($done->fresh()->files_deleted_at->equalTo(now()->subDays(5)))->toBeTrue()
        ->and($missing->fresh()->files_deleted_at)->not->toBeNull();
});

test('it is scheduled daily at 02:00', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'files:cleanup'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 2 * * *');
});
