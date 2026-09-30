<?php

use App\Models\User;

test('login screen can be rendered with a single student id or username field', function () {
    $this->get('/login')
        ->assertStatus(200)
        ->assertSee('Student ID or Username')
        ->assertSee('name="login"', false)
        ->assertDontSee('name="email"', false);
});

test('students can log in with their student id', function () {
    $student = User::factory()->create(['student_id' => 'MMC2024-00123']);

    $response = $this->post('/login', ['login' => 'MMC2024-00123', 'password' => 'password']);

    $this->assertAuthenticatedAs($student);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('student id login is case-insensitive and trimmed', function () {
    $student = User::factory()->create(['student_id' => 'MMC2024-00123']);

    $this->post('/login', ['login' => ' mmc2024-00123 ', 'password' => 'password']);

    $this->assertAuthenticatedAs($student);
});

test('staff and admins log in with their username', function (string $state) {
    $user = User::factory()->{$state}()->create(['username' => "{$state}user"]);

    $this->post('/login', ['login' => "{$state}user", 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
})->with(['staff', 'admin']);

test('staff cannot log in with an email address', function () {
    $staff = User::factory()->staff()->create(['email' => 'staff@example.com']);

    $this->post('/login', ['login' => 'staff@example.com', 'password' => 'password']);

    $this->assertGuest();
});

test('users are redirected to their role home', function (string $state, string $path) {
    $user = User::factory()->{$state}()->create();
    $login = $user->student_id ?? $user->username;

    $this->post('/login', ['login' => $login, 'password' => 'password'])
        ->assertRedirect($path);
})->with([
    'student' => ['student', '/dashboard'],
    'staff' => ['staff', '/staff/station'],
    'admin' => ['admin', '/admin'],
]);

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->student_id, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['login' => trans('auth.failed')]);

    $this->assertGuest();
});

test('unknown accounts get the same generic error', function () {
    $this->post('/login', ['login' => 'nobody', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => trans('auth.failed')]);

    $this->assertGuest();
});

test('pending, rejected and disabled accounts get a clear message and no session', function (string $state, string $message) {
    $user = User::factory()->{$state}()->create(['rejection_reason' => 'ID photo is unreadable']);

    $response = $this->post('/login', ['login' => $user->student_id, 'password' => 'password']);

    $this->assertGuest();
    $response->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain($message);
})->with([
    'pending' => ['pending', 'waiting for approval'],
    'rejected' => ['rejected', 'ID photo is unreadable'],
    'disabled' => ['disabled', 'disabled'],
]);

test('account status is not revealed without the correct password', function () {
    $user = User::factory()->pending()->create();

    $this->post('/login', ['login' => $user->student_id, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['login' => trans('auth.failed')]);
});

test('login is locked after 5 failures for 15 minutes', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['login' => $user->student_id, 'password' => 'wrong-password']);
    }

    $this->post('/login', ['login' => $user->student_id, 'password' => 'password'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();
    expect(session('errors')->first('login'))->toContain('Too many login attempts');

    $this->travel(14)->minutes();
    $this->post('/login', ['login' => $user->student_id, 'password' => 'password']);
    $this->assertGuest();

    $this->travel(2)->minutes();
    $this->post('/login', ['login' => $user->student_id, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

test('four failures do not lock the account', function () {
    $user = User::factory()->create();

    foreach (range(1, 4) as $attempt) {
        $this->post('/login', ['login' => $user->student_id, 'password' => 'wrong-password']);
    }

    $this->post('/login', ['login' => $user->student_id, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
