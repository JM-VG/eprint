<?php

use App\Models\Announcement;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function announcementData(array $overrides = []): array
{
    return array_merge([
        'title' => 'Closed on Friday',
        'body' => 'Both printing rooms are closed for the university holiday.',
        'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
        'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ], $overrides);
}

describe('admin CRUD', function () {
    it('is admin only', function (string $state) {
        $actor = User::factory()->{$state}()->create();

        $this->actingAs($actor)->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($actor)->post(route('admin.announcements.store'), announcementData())->assertForbidden();
    })->with(['student', 'staff']);

    it('creates an announcement credited to the admin', function () {
        $this->actingAs($this->admin)->get(route('admin.announcements.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), announcementData())
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success');

        $a = Announcement::sole();
        expect($a->title)->toBe('Closed on Friday')
            ->and($a->created_by)->toBe($this->admin->id)
            ->and($a->starts_at)->not->toBeNull();
    });

    it('lists, edits and deletes announcements', function () {
        $a = Announcement::factory()->create(['title' => 'Old title']);

        $this->actingAs($this->admin)->get(route('admin.announcements.index'))->assertSee('Old title');
        $this->actingAs($this->admin)->get(route('admin.announcements.edit', $a))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.announcements.update', $a), announcementData(['title' => 'New title']))
            ->assertRedirect(route('admin.announcements.index'));
        expect($a->fresh()->title)->toBe('New title');

        $this->actingAs($this->admin)->delete(route('admin.announcements.destroy', $a))->assertRedirect();
        expect(Announcement::count())->toBe(0);
    });

    it('validates input', function (array $overrides, string $field) {
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), announcementData($overrides))
            ->assertSessionHasErrors($field);
    })->with([
        'title required' => [['title' => ''], 'title'],
        'body required' => [['body' => ''], 'body'],
        'body too long' => [['body' => str_repeat('x', 2001)], 'body'],
        'ends before start' => [['starts_at' => '2026-10-05T08:00', 'ends_at' => '2026-10-01T08:00'], 'ends_at'],
        'bad date' => [['starts_at' => 'soon'], 'starts_at'],
    ]);

    it('allows open-ended announcements', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), announcementData(['starts_at' => '', 'ends_at' => '']))
            ->assertSessionHasNoErrors();

        expect(Announcement::sole()->starts_at)->toBeNull()
            ->and(Announcement::sole()->ends_at)->toBeNull();
    });
});

describe('banner', function () {
    it('shows active announcements on guest and signed-in pages', function () {
        Announcement::factory()->create(['title' => 'Now showing', 'body' => 'Body text', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

        $this->get('/login')->assertSee('Now showing')->assertSee('Body text')->assertSee('data-announcement-banner', false);
        $this->actingAs(User::factory()->create())->get('/profile')->assertSee('Now showing');
    });

    it('hides announcements that have not started or already ended', function () {
        Announcement::factory()->create(['title' => 'Future', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        Announcement::factory()->create(['title' => 'Past', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
        Announcement::factory()->create(['title' => 'Open ended', 'starts_at' => null, 'ends_at' => null]);

        $this->get('/login')->assertDontSee('Future')->assertDontSee('Past')->assertSee('Open ended');
    });

    it('escapes announcement text', function () {
        Announcement::factory()->create(['title' => '<script>alert(1)</script>', 'starts_at' => null, 'ends_at' => null]);

        $this->get('/login')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    });

    it('renders nothing when there are no active announcements', function () {
        $this->get('/login')->assertDontSee('data-announcement-banner', false);
    });
});
