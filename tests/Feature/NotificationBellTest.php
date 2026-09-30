<?php

use App\Enums\JobStatus;
use App\Models\PrintJob;
use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\JobReady;
use App\Services\JobStatusService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->student = User::factory()->create();
    $this->staff = User::factory()->staff()->create();
    $this->job = PrintJob::factory()->create([
        'user_id' => $this->student->id,
        'status' => JobStatus::Printing,
        'claim_code' => 'EP-RDY2',
    ]);
});

describe('JobReady', function () {
    it('is sent to the owner when the job becomes ready', function () {
        Notification::fake();

        app(JobStatusService::class)->transition($this->job, JobStatus::Ready, $this->staff);

        Notification::assertSentTo($this->student, JobReady::class, fn (JobReady $n) => $n->job->is($this->job));
        Notification::assertNotSentTo($this->staff, JobReady::class);
    });

    it('is not sent for other status changes', function () {
        Notification::fake();
        $pending = PrintJob::factory()->create(['user_id' => $this->student->id]);

        app(JobStatusService::class)->reject($pending, $this->staff, 'Blurry');

        Notification::assertNothingSent();
    });

    it('stores the claim code, location and a link to the job', function () {
        app(JobStatusService::class)->transition($this->job, JobStatus::Ready, $this->staff);

        $data = $this->student->notifications()->sole()->data;

        expect($data['claim_code'])->toBe('EP-RDY2')
            ->and($data['message'])->toContain('EP-RDY2')->toContain($this->job->location->name)
            ->and($data['url'])->toBe(route('student.jobs.show', $this->job));
    });
});

describe('unread count endpoint', function () {
    it('returns the unread count for the signed-in user only', function () {
        $this->student->notify(new AccountApproved);
        $this->student->notify(new AccountApproved);
        $this->staff->notify(new AccountApproved);
        $this->student->notifications()->first()->markAsRead();

        $this->actingAs($this->student)
            ->getJson(route('api.notifications.count'))
            ->assertOk()
            ->assertExactJson(['unread' => 1]);
    });

    it('requires login', function () {
        $this->getJson(route('api.notifications.count'))->assertUnauthorized();
    });
});

describe('bell and notifications page', function () {
    it('shows the bell with the unread count on every signed-in page', function () {
        $this->student->notify(new AccountApproved);

        $this->actingAs($this->student)
            ->get('/profile')
            ->assertSee('data-notification-bell', false)
            ->assertSee('data-endpoint="'.route('api.notifications.count').'"', false)
            ->assertSee(route('notifications.index'), false);

        expect(file_get_contents(resource_path('js/app.js')))->toContain('startNotificationBell');
    });

    it('lists the user\'s notifications and opening one marks it read and goes to the job', function () {
        app(JobStatusService::class)->transition($this->job, JobStatus::Ready, $this->staff);
        $notification = $this->student->notifications()->sole();

        $this->actingAs($this->student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('EP-RDY2')
            ->assertSee(route('notifications.read', $notification->id), false);

        $this->actingAs($this->student)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('student.jobs.show', $this->job));

        expect($notification->fresh()->read_at)->not->toBeNull();
    });

    it('marks one notification as read, only if it is yours', function () {
        $this->student->notify(new AccountApproved);
        $this->staff->notify(new AccountApproved);
        $mine = $this->student->notifications()->sole();
        $theirs = $this->staff->notifications()->sole();

        $this->actingAs($this->student)->post(route('notifications.read', $theirs->id))->assertNotFound();
        $this->actingAs($this->student)->post(route('notifications.read', $mine->id))->assertRedirect();

        expect($mine->fresh()->read_at)->not->toBeNull()
            ->and($theirs->fresh()->read_at)->toBeNull();
    });

    it('marks all as read', function () {
        $this->student->notify(new AccountApproved);
        $this->student->notify(new AccountApproved);

        $this->actingAs($this->student)->post(route('notifications.read-all'))->assertRedirect();

        expect($this->student->unreadNotifications()->count())->toBe(0);
    });
});
