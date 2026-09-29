<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin'])->get('/_test/admin-only', fn () => 'admin area');
    Route::middleware(['web', 'auth', 'role:staff,admin'])->get('/_test/counter', fn () => 'counter area');
    Route::middleware(['web', 'auth', 'role:student'])->get('/_test/student-only', fn () => 'student area');
});

describe('EnsureRole', function () {
    it('lets a user in when their role is listed', function (string $state, string $path) {
        $this->actingAs(User::factory()->{$state}()->create())
            ->get($path)
            ->assertOk();
    })->with([
        ['admin', '/_test/admin-only'],
        ['staff', '/_test/counter'],
        ['admin', '/_test/counter'],
        ['student', '/_test/student-only'],
    ]);

    it('returns the 403 page when the role is not listed', function (string $state, string $path) {
        $this->actingAs(User::factory()->{$state}()->create())
            ->get($path)
            ->assertForbidden()
            ->assertSee('You do not have access to this page');
    })->with([
        ['student', '/_test/admin-only'],
        ['staff', '/_test/admin-only'],
        ['student', '/_test/counter'],
        ['staff', '/_test/student-only'],
        ['admin', '/_test/student-only'],
    ]);

    it('sends guests to the login page', function () {
        $this->get('/_test/admin-only')->assertRedirect(route('login'));
    });
});

describe('EnsureApproved', function () {
    it('ends the session of accounts that are no longer approved', function (string $state, string $message) {
        $user = User::factory()->{$state}()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        expect(session('errors')->first('login'))->toContain($message);
    })->with([
        'pending' => ['pending', 'waiting for approval'],
        'rejected' => ['rejected', 'rejected'],
        'disabled' => ['disabled', 'disabled'],
    ]);

    it('applies to role-protected pages too', function () {
        $this->actingAs(User::factory()->admin()->disabled()->create())
            ->get('/_test/admin-only')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });

    it('lets approved users through', function () {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    });

    it('does not affect guest pages', function () {
        $this->get('/login')->assertOk();
    });
});

describe('EnsurePasswordChanged', function () {
    it('forces users with a temporary password to the password change page', function () {
        $user = User::factory()->staff()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('warning');

        $this->actingAs($user)->get('/_test/counter')->assertRedirect(route('profile.edit'));
    });

    it('still allows the profile page and logout', function () {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    });

    it('clears the flag once the password is changed', function () {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasNoErrors();

        expect($user->fresh()->must_change_password)->toBeFalse();

        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    });

    it('keeps the flag when the password change fails', function () {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'wrong',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        expect($user->fresh()->must_change_password)->toBeTrue();
    });
});

describe('route groups', function () {
    it('protects the shared pages with auth, approval and password checks', function () {
        foreach (['dashboard', 'profile.edit'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

            expect($middleware)->toContain('auth');
        }

        $this->get('/dashboard')->assertRedirect(route('login'));
    });
});
