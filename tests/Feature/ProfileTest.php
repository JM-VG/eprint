<?php

use App\Models\User;

test('profile page shows the login id, role and password form', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSee($user->student_id ?? $user->username)
        ->assertSee(ucfirst($user->role->value))
        ->assertSee('name="first_name"', false)
        ->assertSee('name="last_name"', false)
        ->assertSee('name="current_password"', false)
        ->assertDontSee('name="email"', false);
})->with(['student', 'staff', 'admin']);

test('names can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', ['first_name' => 'Ana', 'last_name' => 'Reyes'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();
    expect($user->first_name)->toBe('Ana')
        ->and($user->last_name)->toBe('Reyes')
        ->and($user->name)->toBe('Ana Reyes');
});

test('names are required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', ['first_name' => '', 'last_name' => ''])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

test('the profile form cannot change login id, role or status', function () {
    $user = User::factory()->create(['student_id' => 'MMC2024-00001']);

    $this->actingAs($user)->patch('/profile', [
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'student_id' => 'MMC2024-99999',
        'username' => 'admin2',
        'role' => 'admin',
        'status' => 'approved',
        'must_change_password' => true,
    ]);

    $user->refresh();
    expect($user->student_id)->toBe('MMC2024-00001')
        ->and($user->username)->toBeNull()
        ->and($user->role->value)->toBe('student')
        ->and($user->must_change_password)->toBeFalse();
});

test('users with a temporary password see why they are on the profile page', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee('temporary password');
});

test('users cannot delete their own account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertDontSee('Delete Account');
    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});
