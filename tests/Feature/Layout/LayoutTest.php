<?php

use App\Models\User;

function menuMarker(string $key): string
{
    return 'data-menu="'.$key.'"';
}

it('shows students only the student menu', function () {
    $response = $this->actingAs(User::factory()->create())->get('/dashboard');

    $response->assertOk();
    foreach (['dashboard', 'new-print-job', 'new-photo-job', 'my-jobs', 'profile'] as $key) {
        $response->assertSee(menuMarker($key), false);
    }
    foreach (['station', 'queue', 'claim', 'walkin-sale', 'my-commission', 'students', 'price-list', 'settings', 'payouts'] as $key) {
        $response->assertDontSee(menuMarker($key), false);
    }
});

it('shows staff only the staff menu', function () {
    $response = $this->actingAs(User::factory()->staff()->create())->get('/dashboard');

    $response->assertOk();
    foreach (['dashboard', 'station', 'queue', 'claim', 'walkin-sale', 'submit-job', 'my-commission', 'profile'] as $key) {
        $response->assertSee(menuMarker($key), false);
    }
    foreach (['new-print-job', 'new-photo-job', 'my-jobs', 'students', 'staff', 'price-list', 'settings', 'payouts', 'sales-report', 'shift-history', 'audit-log', 'announcements'] as $key) {
        $response->assertDontSee(menuMarker($key), false);
    }
});

it('shows admins the staff menu plus the admin menu', function () {
    $response = $this->actingAs(User::factory()->admin()->create())->get('/dashboard');

    $response->assertOk();
    foreach ([
        'dashboard', 'station', 'queue', 'claim', 'walkin-sale', 'submit-job', 'my-commission',
        'students', 'staff', 'price-list', 'settings', 'sales-report', 'payouts',
        'shift-history', 'audit-log', 'announcements', 'profile',
    ] as $key) {
        $response->assertSee(menuMarker($key), false);
    }
    foreach (['new-print-job', 'new-photo-job', 'my-jobs'] as $key) {
        $response->assertDontSee(menuMarker($key), false);
    }
});

it('renders the availability banner placeholder on app and guest pages', function () {
    $this->get('/login')->assertOk()->assertSee('id="availability-banner"', false);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertSee('id="availability-banner"', false);
});

it('renders flash messages for each type', function (string $type) {
    $this->actingAs(User::factory()->create())
        ->withSession([$type => "Flash {$type} message"])
        ->get('/dashboard')
        ->assertSee("Flash {$type} message")
        ->assertSee('data-flash="'.$type.'"', false);
})->with(['success', 'error', 'warning', 'info']);

it('renders no flash container when there is no flash message', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertDontSee('data-flash=', false);
});
