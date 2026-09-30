<?php

use App\Enums\JobStatus;
use App\Models\JobLog;
use App\Models\PrintJob;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->maria = User::factory()->staff()->create(['name' => 'Maria Santos']);
    $this->jobA = PrintJob::factory()->create(['claim_code' => 'EP-AAA2']);
    $this->jobB = PrintJob::factory()->create(['claim_code' => 'EP-BBB3']);
});

function logEntry(PrintJob $job, ?User $user, ?JobStatus $from, JobStatus $to, string $at, ?string $note = null): JobLog
{
    return JobLog::factory()->for($job)->create([
        'user_id' => $user?->id,
        'from_status' => $from,
        'to_status' => $to,
        'note' => $note,
        'created_at' => $at,
    ]);
}

test('only admins can read the audit log', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get(route('admin.audit.index'))->assertForbidden();
})->with(['student', 'staff']);

test('it lists job log entries newest first with job, actor, change and note', function () {
    logEntry($this->jobA, $this->maria, JobStatus::Pending, JobStatus::Accepted, '2026-09-01 09:00:00');
    logEntry($this->jobA, $this->maria, JobStatus::Accepted, JobStatus::Rejected, '2026-09-02 10:00:00', 'Blurry scan');

    $this->actingAs($this->admin)
        ->get(route('admin.audit.index'))
        ->assertOk()
        ->assertSeeInOrder(['Rejected', 'Blurry scan', 'Accepted'])
        ->assertSee('EP-AAA2')
        ->assertSee('Maria Santos');
});

test('it filters by claim code, actor, status and date range', function (array $query, int $expected) {
    logEntry($this->jobA, $this->maria, JobStatus::Pending, JobStatus::Accepted, '2026-09-01 09:00:00');
    logEntry($this->jobA, $this->maria, JobStatus::Accepted, JobStatus::Printing, '2026-09-05 09:00:00');
    logEntry($this->jobB, $this->admin, JobStatus::Pending, JobStatus::Rejected, '2026-09-05 11:00:00', 'Wrong file');
    logEntry($this->jobB, null, null, JobStatus::Pending, '2026-08-20 08:00:00', 'Submitted');

    $response = $this->actingAs($this->admin)->get(route('admin.audit.index', $query));

    expect($response->viewData('logs')->total())->toBe($expected);
})->with([
    'claim code' => [['code' => 'ep-aaa2'], 2],
    'status' => [['status' => 'rejected'], 1],
    'date range' => [['from' => '2026-09-01', 'to' => '2026-09-04'], 1],
    'combined' => [['code' => 'EP-BBB3', 'status' => 'pending'], 1],
]);

test('actor filter matches the chosen staff member', function () {
    logEntry($this->jobA, $this->maria, JobStatus::Pending, JobStatus::Accepted, '2026-09-01 09:00:00');
    logEntry($this->jobB, $this->admin, JobStatus::Pending, JobStatus::Rejected, '2026-09-05 11:00:00');

    $response = $this->actingAs($this->admin)->get(route('admin.audit.index', ['user_id' => $this->maria->id]));

    expect($response->viewData('logs')->pluck('print_job_id')->all())->toBe([$this->jobA->id]);
});

test('filters are validated', function (array $query, string $field) {
    $this->actingAs($this->admin)
        ->get(route('admin.audit.index', $query))
        ->assertSessionHasErrors($field);
})->with([
    'bad status' => [['status' => 'lost'], 'status'],
    'range backwards' => [['from' => '2026-09-10', 'to' => '2026-09-01'], 'to'],
    'unknown user' => [['user_id' => 99999], 'user_id'],
]);

test('system entries without an actor show as System', function () {
    logEntry($this->jobA, null, null, JobStatus::Pending, '2026-09-01 09:00:00', 'Imported');

    $this->actingAs($this->admin)->get(route('admin.audit.index'))->assertSee('System');
});
