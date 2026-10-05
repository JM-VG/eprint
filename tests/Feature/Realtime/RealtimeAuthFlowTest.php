<?php

use App\Auth\RealtimeUser;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountApproved;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('restores the signed-in user from the realtime database on later requests', function () {
    $user = User::factory()->staff()->create(['username' => 'staff1', 'password' => 'secret-password', 'status' => UserStatus::Approved]);

    $this->post('/login', ['login' => 'staff1', 'password' => 'secret-password'])->assertRedirect();

    // A new request starts with no user in memory: the guard asks the rtdb provider.
    Auth::forgetGuards();

    $this->get('/dashboard')->assertOk();

    expect(Auth::user())->toBeInstanceOf(RealtimeUser::class)
        ->and(Auth::user())->toBeInstanceOf(User::class)
        ->and(Auth::id())->toBe($user->id);
});

it('stores notifications for the signed-in user under the User type', function () {
    $user = User::factory()->create();

    app('auth')->createUserProvider('rtdb')->retrieveById($user->id)->notify(new AccountApproved);

    expect($user->notifications()->count())->toBe(1)
        ->and(DB::table('notifications')->value('notifiable_type'))->toBe(User::class);
});

it('runs the database cache store, with working locks, on the realtime database', function () {
    $cache = Cache::store('database');

    $cache->put('greeting', 'hello', 60);
    $cache->put('visits', 0, 60);
    $cache->increment('visits');
    $cache->increment('visits');

    $first = $cache->lock('job', 10);
    $second = $cache->lock('job', 10);

    expect($cache->get('greeting'))->toBe('hello')
        ->and((int) $cache->get('visits'))->toBe(2)
        ->and($first->get())->toBeTrue()
        ->and($second->get())->toBeFalse();

    $first->release();

    expect($second->get())->toBeTrue();
});

it('runs the database session handler on the realtime database', function () {
    $handler = new DatabaseSessionHandler(DB::connection(), 'sessions', 120, app());
    $id = str_repeat('a', 40);

    $handler->write($id, 'payload-1');
    $handler->write($id, 'payload-2');

    expect($handler->read($id))->toBe('payload-2')
        ->and(DB::table('sessions')->count())->toBe(1);

    $handler->destroy($id);

    expect($handler->read($id))->toBe('');
});
