<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewSignup;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function signupData(array $overrides = []): array
{
    return array_merge([
        'student_id' => 'MMC2024-00123',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('registration screen can be rendered with the student fields', function () {
    $response = $this->get('/register');

    $response->assertStatus(200)
        ->assertSee('name="student_id"', false)
        ->assertSee('name="first_name"', false)
        ->assertSee('name="last_name"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertDontSee('name="email"', false);
});

test('new students can register and start as pending', function () {
    $response = $this->post('/register', signupData());

    $response->assertRedirect(route('register.pending', absolute: false));
    $this->assertGuest();

    $user = User::where('student_id', 'MMC2024-00123')->sole();

    expect($user->role)->toBe(Role::Student)
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->first_name)->toBe('Juan')
        ->and($user->last_name)->toBe('Dela Cruz')
        ->and($user->name)->toBe('Juan Dela Cruz')
        ->and($user->email)->toBeNull()
        ->and($user->username)->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

test('the waiting page is shown after signup', function () {
    $this->get(route('register.pending'))
        ->assertOk()
        ->assertSee('waiting for approval');
});

test('student id is stored uppercase', function () {
    $this->post('/register', signupData(['student_id' => '  mmc2024-00123 ']));

    expect(User::sole()->student_id)->toBe('MMC2024-00123');
});

test('student id must match the pattern', function (string $studentId) {
    $this->from('/register')
        ->post('/register', signupData(['student_id' => $studentId]))
        ->assertRedirect('/register')
        ->assertSessionHasErrors('student_id');

    expect(User::count())->toBe(0);
})->with(['MMC2024-0012', 'MM2024-00123', 'MMC202400123', 'MMC2024-001234', '']);

test('student id pattern comes from settings', function () {
    Setting::create(['key' => 'student_id_pattern', 'value' => '^\d{2}-\d{4}$']);

    $this->post('/register', signupData(['student_id' => 'MMC2024-00123']))
        ->assertSessionHasErrors('student_id');

    $this->post('/register', signupData(['student_id' => '24-1234']))
        ->assertSessionHasNoErrors();

    expect(User::sole()->student_id)->toBe('24-1234');
});

test('an invalid pattern in settings falls back to the default pattern', function () {
    Setting::create(['key' => 'student_id_pattern', 'value' => '([unclosed']);

    $this->post('/register', signupData())->assertSessionHasNoErrors();

    expect(User::count())->toBe(1);
});

test('duplicate student id shows the printing room message', function () {
    User::factory()->create(['student_id' => 'MMC2024-00123']);

    $this->post('/register', signupData(['student_id' => 'mmc2024-00123']))
        ->assertSessionHasErrors([
            'student_id' => 'This Student ID is already registered. If this is your ID, visit the printing room.',
        ]);

    expect(User::count())->toBe(1);
});

test('names and password confirmation are required', function () {
    $this->post('/register', signupData([
        'first_name' => '',
        'last_name' => '',
        'password_confirmation' => 'different',
    ]))->assertSessionHasErrors(['first_name', 'last_name', 'password']);

    expect(User::count())->toBe(0);
});

test('signup cannot set role, status or approval fields', function () {
    $this->post('/register', signupData([
        'role' => 'admin',
        'status' => 'approved',
        'username' => 'sneaky',
        'must_change_password' => true,
        'approved_by' => 1,
    ]));

    $user = User::sole();

    expect($user->role)->toBe(Role::Student)
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->username)->toBeNull()
        ->and($user->approved_by)->toBeNull();
});

test('approved admins get a NewSignup notification', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $disabledAdmin = User::factory()->admin()->disabled()->create();
    $staff = User::factory()->staff()->create();

    $this->post('/register', signupData());

    $student = User::where('student_id', 'MMC2024-00123')->sole();

    Notification::assertSentTo($admin, NewSignup::class, fn (NewSignup $n) => $n->student->is($student));
    Notification::assertNotSentTo([$disabledAdmin, $staff, $student], NewSignup::class);
});

test('NewSignup is stored in the admin database notifications', function () {
    $admin = User::factory()->admin()->create();

    $this->post('/register', signupData());

    $notification = $admin->notifications()->sole();

    expect($notification->type)->toBe(NewSignup::class)
        ->and($notification->data['student_id'])->toBe('MMC2024-00123')
        ->and($notification->data['name'])->toBe('Juan Dela Cruz');
});

test('logged in users cannot open the registration screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/register')
        ->assertRedirect();
});
