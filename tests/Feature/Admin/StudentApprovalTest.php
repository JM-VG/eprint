<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\AccountRejected;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('only admins can open the approval queue', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('admin.students.index'))
        ->assertForbidden();
})->with(['student', 'staff']);

test('the queue lists pending students by default', function () {
    $pending = User::factory()->pending()->create(['student_id' => 'AAA2024-00001']);
    $approved = User::factory()->create(['student_id' => 'BBB2024-00002']);

    $this->actingAs($this->admin)
        ->get(route('admin.students.index'))
        ->assertOk()
        ->assertSee('AAA2024-00001')
        ->assertDontSee('BBB2024-00002');
});

test('the queue can be filtered by status', function (string $status) {
    $match = User::factory()->create(['student_id' => 'MAT2024-00001', 'status' => $status]);
    $other = User::factory()->pending()->create(['student_id' => 'OTH2024-00002']);

    $this->actingAs($this->admin)
        ->get(route('admin.students.index', ['status' => $status]))
        ->assertOk()
        ->assertSee('MAT2024-00001')
        ->assertDontSee('OTH2024-00002');
})->with(['approved', 'rejected', 'disabled']);

test('the queue never lists staff or admins', function () {
    User::factory()->staff()->pending()->create(['username' => 'staffpending']);

    $this->actingAs($this->admin)
        ->get(route('admin.students.index'))
        ->assertDontSee('staffpending');
});

test('admins can approve a pending student', function () {
    Notification::fake();
    $student = User::factory()->pending()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.students.approve', $student))
        ->assertRedirect()
        ->assertSessionHas('success');

    $student->refresh();
    expect($student->status)->toBe(UserStatus::Approved)
        ->and($student->approved_by)->toBe($this->admin->id)
        ->and($student->approved_at)->not->toBeNull()
        ->and($student->rejection_reason)->toBeNull();

    Notification::assertSentTo($student, AccountApproved::class);
});

test('an approved student can then log in', function () {
    $student = User::factory()->pending()->create();

    $this->actingAs($this->admin)->post(route('admin.students.approve', $student));
    auth()->logout();

    $this->post('/login', ['login' => $student->student_id, 'password' => 'password']);
    $this->assertAuthenticatedAs($student);
});

test('admins can reject a student with a reason', function () {
    Notification::fake();
    $student = User::factory()->pending()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.students.reject', $student), ['reason' => 'Student ID not found in registrar list'])
        ->assertSessionHas('success');

    $student->refresh();
    expect($student->status)->toBe(UserStatus::Rejected)
        ->and($student->rejection_reason)->toBe('Student ID not found in registrar list');

    Notification::assertSentTo($student, AccountRejected::class,
        fn (AccountRejected $n) => $n->toArray($student)['reason'] === 'Student ID not found in registrar list');
});

test('rejecting requires a reason', function () {
    $student = User::factory()->pending()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.students.reject', $student), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($student->fresh()->status)->toBe(UserStatus::Pending);
});

test('approve and reject only work on student accounts', function () {
    $staff = User::factory()->staff()->pending()->create();

    $this->actingAs($this->admin)->post(route('admin.students.approve', $staff))->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.students.reject', $staff), ['reason' => 'x'])->assertForbidden();

    expect($staff->fresh()->status)->toBe(UserStatus::Pending);
});

test('admins can disable any other account', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.disable', $user))
        ->assertSessionHas('success');

    expect($user->fresh()->status)->toBe(UserStatus::Disabled);
})->with(['student', 'staff', 'admin']);

test('admins cannot disable themselves', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.disable', $this->admin))
        ->assertForbidden();

    expect($this->admin->fresh()->status)->toBe(UserStatus::Approved);
});

test('a disabled student can be approved again', function () {
    $student = User::factory()->disabled()->create();

    $this->actingAs($this->admin)->post(route('admin.students.approve', $student));

    expect($student->fresh()->status)->toBe(UserStatus::Approved);
});

test('non admins cannot approve, reject or disable', function (string $state) {
    $actor = User::factory()->{$state}()->create();
    $student = User::factory()->pending()->create();

    $this->actingAs($actor)->post(route('admin.students.approve', $student))->assertForbidden();
    $this->actingAs($actor)->post(route('admin.students.reject', $student), ['reason' => 'x'])->assertForbidden();
    $this->actingAs($actor)->post(route('admin.users.disable', $student))->assertForbidden();

    expect($student->fresh()->status)->toBe(UserStatus::Pending);
})->with(['student', 'staff']);

test('approval notifications are stored for the student bell', function () {
    $student = User::factory()->pending()->create();

    $this->actingAs($this->admin)->post(route('admin.students.approve', $student));

    expect($student->notifications()->sole()->type)->toBe(AccountApproved::class);
});
