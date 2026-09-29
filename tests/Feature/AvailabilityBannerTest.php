<?php

use App\Models\Location;
use App\Models\StationSession;
use App\Models\User;

beforeEach(function () {
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room', 'slug' => 'main-it']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office', 'slug' => 'ccs-office']);
});

test('the availability endpoint is public and lists both locations', function () {
    StationSession::factory()->for($this->main)->create(['last_seen_at' => now()]);

    $this->getJson(route('api.availability'))
        ->assertOk()
        ->assertExactJson([
            'locations' => [
                ['id' => $this->main->id, 'name' => 'Main IT Printing Room', 'slug' => 'main-it', 'open' => true, 'staff_count' => 1],
                ['id' => $this->ccs->id, 'name' => 'CCS Office', 'slug' => 'ccs-office', 'open' => false, 'staff_count' => 0],
            ],
        ]);
});

test('the availability endpoint is rate limited', function () {
    $route = app('router')->getRoutes()->getByName('api.availability');

    expect(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with($m, 'throttle')))->toBeTrue();
});

test('the banner shows the current status on the login page', function () {
    StationSession::factory()->for($this->ccs)->create(['last_seen_at' => now()]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('id="availability-banner"', false)
        ->assertSee('data-endpoint="'.route('api.availability').'"', false)
        ->assertSeeInOrder(['Main IT Printing Room', 'Closed', 'CCS Office', 'Open']);
});

test('the banner is on signed-in pages for every role', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/profile')
        ->assertSee('id="availability-banner"', false)
        ->assertSee('Main IT Printing Room');
})->with(['student', 'staff', 'admin']);

test('the banner is on guest pages like signup', function () {
    $this->get('/register')->assertSee('id="availability-banner"', false);
});

test('the home page sends visitors to login or their dashboard', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});

test('the banner script polls every 15 seconds', function () {
    $script = file_get_contents(resource_path('js/availability.js'));

    expect($script)->toContain('15_000')
        ->and(file_get_contents(resource_path('js/app.js')))->toContain('startAvailabilityBanner');
});
