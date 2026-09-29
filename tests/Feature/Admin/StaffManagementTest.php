<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['username' => 'boss']);
});

function staffData(array $overrides = []): array
{
    return array_merge([
        'username' => 'maria.santos',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'role' => 'staff',
    ], $overrides);
}

function flashedTemporaryPassword(): string
{
    preg_match('/temporary password[^:]*: (\S+)/i', (string) session('success'), $m);

    return $m[1] ?? '';
}

test('only admins can manage staff', function (string $state) {
    $actor = User::factory()->{$state}()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($actor)->get(route('admin.staff.index'))->assertForbidden();
    $this->actingAs($actor)->post(route('admin.staff.store'), staffData())->assertForbidden();
    $this->actingAs($actor)->put(route('admin.staff.update', $staff), staffData())->assertForbidden();
    $this->actingAs($actor)->post(route('admin.users.reset-password', $staff))->assertForbidden();
})->with(['student', 'staff']);

test('the staff list shows staff and admins but not students', function () {
    User::factory()->staff()->create(['username' => 'counter.one']);
    User::factory()->create(['student_id' => 'STU2024-00001']);

    $this->actingAs($this->admin)
        ->get(route('admin.staff.index'))
        ->assertOk()
        ->assertSee('counter.one')
        ->assertSee('boss')
        ->assertDontSee('STU2024-00001');
});

test('the create form renders', function () {
    $this->actingAs($this->admin)->get(route('admin.staff.create'))->assertOk()->assertSee('name="username"', false);
});

test('admins create staff with a temporary password they must change', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), staffData())
        ->assertRedirect(route('admin.staff.index'))
        ->assertSessionHas('success');

    $staff = User::where('username', 'maria.santos')->sole();
    $password = flashedTemporaryPassword();

    expect($staff->role)->toBe(Role::Staff)
        ->and($staff->status)->toBe(UserStatus::Approved)
        ->and($staff->must_change_password)->toBeTrue()
        ->and($staff->student_id)->toBeNull()
        ->and($staff->name)->toBe('Maria Santos')
        ->and($staff->approved_by)->toBe($this->admin->id)
        ->and(strlen($password))->toBeGreaterThanOrEqual(10)
        ->and(Hash::check($password, $staff->password))->toBeTrue();
});

test('new staff log in with the temporary password and are forced to change it', function () {
    $this->actingAs($this->admin)->post(route('admin.staff.store'), staffData());
    $password = flashedTemporaryPassword();
    auth()->logout();

    $this->post('/login', ['login' => 'maria.santos', 'password' => $password]);
    $this->assertAuthenticated();
    $this->get('/dashboard')->assertRedirect(route('profile.edit'));
});

test('admins can create another admin', function () {
    $this->actingAs($this->admin)->post(route('admin.staff.store'), staffData(['role' => 'admin']));

    expect(User::where('username', 'maria.santos')->sole()->role)->toBe(Role::Admin);
});

test('staff cannot be created as students or with unknown roles', function (string $role) {
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), staffData(['role' => $role]))
        ->assertSessionHasErrors('role');
})->with(['student', 'owner', '']);

test('usernames must be unique', function () {
    User::factory()->staff()->create(['username' => 'maria.santos']);

    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), staffData())
        ->assertSessionHasErrors('username');
});

test('usernames may not match any student id', function () {
    User::factory()->create(['student_id' => 'MMC2024-00123']);

    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), staffData(['username' => 'mmc2024-00123']))
        ->assertSessionHasErrors('username');
});

test('usernames may not look like a student id', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), staffData(['username' => 'MMC2024-99999']))
        ->assertSessionHasErrors('username');
});

test('usernames are limited to letters, numbers, dots, dashes and underscores', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.staff.store'), staffData(['username' => 'bad name!']))
        ->assertSessionHasErrors('username');
});

test('admins can edit staff details and role', function () {
    $staff = User::factory()->staff()->create(['username' => 'old.name']);

    $this->actingAs($this->admin)->get(route('admin.staff.edit', $staff))->assertOk();

    $this->actingAs($this->admin)
        ->put(route('admin.staff.update', $staff), staffData(['username' => 'new.name', 'first_name' => 'Ana', 'role' => 'admin']))
        ->assertRedirect(route('admin.staff.index'));

    $staff->refresh();
    expect($staff->username)->toBe('new.name')
        ->and($staff->first_name)->toBe('Ana')
        ->and($staff->name)->toBe('Ana Santos')
        ->and($staff->role)->toBe(Role::Admin);
});

test('editing keeps the same username without a uniqueness error', function () {
    $staff = User::factory()->staff()->create(['username' => 'same.name']);

    $this->actingAs($this->admin)
        ->put(route('admin.staff.update', $staff), staffData(['username' => 'same.name']))
        ->assertSessionHasNoErrors();
});

test('admins cannot change their own role', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.staff.update', $this->admin), staffData(['username' => 'boss', 'role' => 'staff']))
        ->assertSessionHasErrors('role');

    expect($this->admin->fresh()->role)->toBe(Role::Admin);
});

test('students cannot be edited through staff management', function () {
    $student = User::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.staff.edit', $student))->assertNotFound();
    $this->actingAs($this->admin)->put(route('admin.staff.update', $student), staffData())->assertNotFound();
});

test('admins can disable and re-enable staff', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($this->admin)->post(route('admin.users.disable', $staff));
    expect($staff->fresh()->status)->toBe(UserStatus::Disabled);

    $this->actingAs($this->admin)->post(route('admin.users.enable', $staff))->assertSessionHas('success');
    expect($staff->fresh()->status)->toBe(UserStatus::Approved);
});

test('enable only restores disabled accounts and cannot bypass the approval queue', function (string $state) {
    $student = User::factory()->{$state}()->create();

    $this->actingAs($this->admin)->post(route('admin.users.enable', $student))->assertForbidden();

    expect($student->fresh()->status->value)->toBe($state);
})->with(['pending', 'rejected']);

test('admins can reset any other account to a temporary password', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-password', $user))
        ->assertSessionHas('success');

    $password = flashedTemporaryPassword();
    $user->refresh();

    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and(Hash::check($password, $user->password))->toBeTrue();
})->with(['student', 'staff', 'admin']);

test('admins cannot reset or enable their own account here', function () {
    $this->actingAs($this->admin)->post(route('admin.users.reset-password', $this->admin))->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.users.enable', $this->admin))->assertForbidden();
});

test('staff forms cannot set status or student fields', function () {
    $this->actingAs($this->admin)->post(route('admin.staff.store'), staffData([
        'status' => 'pending',
        'student_id' => 'ABC2024-00001',
        'must_change_password' => false,
    ]));

    $staff = User::where('username', 'maria.santos')->sole();
    expect($staff->status)->toBe(UserStatus::Approved)
        ->and($staff->student_id)->toBeNull()
        ->and($staff->must_change_password)->toBeTrue();
});
