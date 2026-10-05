<?php

use App\Enums\CloseReason;
use App\Enums\JobStatus;
use App\Firebase\DatabaseClient;
use App\Firebase\ServiceAccountTokenProvider;
use App\Models\Location;
use App\Models\PrintJob;
use App\Models\User;
use App\Realtime\PublicProjections;
use App\Services\JobStatusService;
use App\Services\StationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->main = Location::factory()->create(['name' => 'Main IT Printing Room', 'slug' => 'main-it']);
    $this->ccs = Location::factory()->create(['name' => 'CCS Office', 'slug' => 'ccs-office']);
});

function publicNode(string $path): mixed
{
    return app(DatabaseClient::class)->get($path);
}

it('publishes room availability when staff clock in and out', function () {
    $staff = User::factory()->staff()->create();

    app(StationService::class)->clockIn($staff, $this->ccs);

    $locations = collect(publicNode(PublicProjections::AVAILABILITY)['locations'])->keyBy('id');

    expect($locations[$this->ccs->id])->toMatchArray(['name' => 'CCS Office', 'open' => true, 'staff_count' => 1])
        ->and($locations[$this->main->id]['open'])->toBeFalse();

    app(StationService::class)->close($staff, CloseReason::Manual);

    expect(collect(publicNode(PublicProjections::AVAILABILITY)['locations'])->keyBy('id')[$this->ccs->id]['open'])->toBeFalse();
});

it('publishes pending job counts per location as jobs come and go', function () {
    $job = PrintJob::factory()->create(['location_id' => $this->main->id, 'status' => JobStatus::Pending]);
    PrintJob::factory()->create(['location_id' => $this->main->id, 'status' => JobStatus::Pending]);

    expect(publicNode(PublicProjections::QUEUE.'/pending/'.$this->main->id))->toBe(2)
        ->and(publicNode(PublicProjections::QUEUE.'/pending/'.$this->ccs->id))->toBe(0);

    $staff = User::factory()->staff()->create();
    app(JobStatusService::class)->accept($job, $staff, 3, '6.00');

    expect(publicNode(PublicProjections::QUEUE.'/pending/'.$this->main->id))->toBe(1);
});

it('publishes nothing personal', function () {
    PrintJob::factory()->create(['location_id' => $this->main->id]);
    app(StationService::class)->clockIn(User::factory()->staff()->create(['name' => 'Secret Name']), $this->main);

    $public = json_encode(publicNode('public'));

    expect($public)->not->toContain('Secret Name')
        ->and($public)->not->toContain('claim_code')
        ->and($public)->not->toContain('user_id');
});

it('points the banner and the queue badge at the live streams', function () {
    $staff = User::factory()->staff()->create();
    app(StationService::class)->clockIn($staff, $this->main);
    $databaseUrl = rtrim(config('firebase.web.database_url'), '/');

    $this->actingAs($staff)->get('/dashboard')
        ->assertSee('data-realtime-url="'.$databaseUrl.'/public/availability.json"', false)
        ->assertSee('data-realtime-url="'.$databaseUrl.'/public/queue.json"', false)
        ->assertSee('data-location-id="'.$this->main->id.'"', false);
});

it('keeps polling only when live updates are turned off', function () {
    config(['firebase.realtime_ui' => false]);

    $this->get('/login')
        ->assertSee('id="availability-banner"', false)
        ->assertDontSee('data-realtime-url', false);
});

it('streams in the browser with a polling fallback', function () {
    $realtime = file_get_contents(resource_path('js/realtime.js'));
    $banner = file_get_contents(resource_path('js/availability.js'));
    $badge = file_get_contents(resource_path('js/queue-badge.js'));

    expect($realtime)->toContain('new EventSource(url)')
        ->toContain("addEventListener('put'")
        ->toContain("addEventListener('patch'")
        ->and($banner)->toContain('subscribe(')->toContain('15_000')
        ->and($badge)->toContain('subscribe(')->toContain('15_000');
});

it('only lets browsers read /public', function () {
    $rules = json_decode(file_get_contents(base_path('database.rules.json')), true);

    expect($rules['rules']['.read'])->toBeFalse()
        ->and($rules['rules']['.write'])->toBeFalse()
        ->and($rules['rules']['public'])->toBe(['.read' => true]);
});

it('deploys the security rules and publishes the summaries on request', function () {
    $tokens = Mockery::mock(ServiceAccountTokenProvider::class);
    $tokens->shouldReceive('token')->andReturn('test-token');
    $this->app->instance(ServiceAccountTokenProvider::class, $tokens);
    Http::fake(['*' => Http::response('{}')]);

    $this->artisan('firebase:rules', ['--deploy' => true, '--publish' => true])
        ->expectsOutputToContain('Security rules deployed.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/.settings/rules.json')
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['rules']['public']['.read'] === true);

    expect(publicNode(PublicProjections::AVAILABILITY))->not->toBeNull()
        ->and(publicNode(PublicProjections::QUEUE))->not->toBeNull();
});
