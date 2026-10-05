<?php

use App\Firebase\BatchingDatabaseClient;
use App\Firebase\DatabaseClient;
use App\Realtime\Database\QueryGrammar;
use App\Realtime\Database\RealtimeConnection;
use App\Realtime\Database\UnsupportedQuery;
use App\Realtime\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RtdbTestLocation extends Model
{
    protected $table = 'locations';

    protected $guarded = [];

    public function sessions(): HasMany
    {
        return $this->hasMany(RtdbTestSession::class, 'location_id');
    }
}

class RtdbTestSession extends Model
{
    protected $table = 'station_sessions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(RtdbTestLocation::class, 'location_id');
    }
}

function rtdb(): RealtimeConnection
{
    return DB::connection('rtdb');
}

function rtdbRaw(string $path): mixed
{
    return app(DatabaseClient::class)->get($path);
}

it('stores rows at /{table}/{id} with ids from the table counter', function () {
    $first = rtdb()->table('locations')->insertGetId(['name' => 'Main', 'slug' => 'main']);
    $second = rtdb()->table('locations')->insertGetId(['name' => 'Annex', 'slug' => 'annex']);

    expect([$first, $second])->toBe([1, 2])
        ->and(rtdbRaw('locations/2'))->toBe(['name' => 'Annex', 'slug' => 'annex', 'id' => 2])
        ->and(rtdbRaw('counters/locations'))->toBe(2);
});

it('applies column defaults and types on insert', function () {
    rtdb()->table('services')->insert(['type' => 'document', 'name' => 'B&W', 'unit' => 'page', 'price' => 2, 'pieces_per_set' => '4']);

    expect(rtdbRaw('services/1'))->toMatchArray(['price' => '2.00', 'is_active' => true, 'pieces_per_set' => 4]);
});

it('stores dates as ISO-8601 strings and enums as their value', function () {
    $this->travelTo('2026-10-05 09:30:00');

    $location = RtdbTestLocation::create(['name' => 'Main', 'slug' => 'main']);

    expect(rtdbRaw("locations/{$location->id}/created_at"))->toBe(now()->format(QueryGrammar::DATE_FORMAT))
        ->and($location->fresh()->created_at->equalTo(now()))->toBeTrue();
});

it('evaluates where clauses with SQL precedence', function () {
    foreach (['a', 'b', 'c', 'd'] as $slug) {
        rtdb()->table('locations')->insert(['name' => strtoupper($slug), 'slug' => $slug]);
    }

    $slugs = fn ($query) => $query->pluck('slug')->all();

    expect($slugs(rtdb()->table('locations')->where('slug', 'a')->orWhere('slug', 'c')->where('id', 3)))->toBe(['a', 'c'])
        ->and($slugs(rtdb()->table('locations')->where(fn ($q) => $q->where('slug', 'a')->orWhere('slug', 'b'))->where('id', '>', 1)))->toBe(['b'])
        ->and($slugs(rtdb()->table('locations')->whereIn('id', [2, 4])))->toBe(['b', 'd'])
        ->and($slugs(rtdb()->table('locations')->whereNotIn('id', [2, 4])))->toBe(['a', 'c'])
        ->and($slugs(rtdb()->table('locations')->whereBetween('id', [2, 3])))->toBe(['b', 'c'])
        ->and($slugs(rtdb()->table('locations')->where('name', 'like', 'c%')))->toBe(['c'])
        ->and($slugs(rtdb()->table('locations')->whereNull('created_at')))->toBe(['a', 'b', 'c', 'd']);
});

it('orders, limits and offsets with NULL first', function () {
    rtdb()->table('announcements')->insert([
        ['title' => 'One', 'body' => 'x', 'starts_at' => '2026-10-02 08:00:00'],
        ['title' => 'Two', 'body' => 'x', 'starts_at' => null],
        ['title' => 'Three', 'body' => 'x', 'starts_at' => '2026-10-01 08:00:00'],
    ]);

    expect(rtdb()->table('announcements')->orderBy('starts_at')->pluck('title')->all())->toBe(['Two', 'Three', 'One'])
        ->and(rtdb()->table('announcements')->orderByDesc('starts_at')->limit(2)->pluck('title')->all())->toBe(['One', 'Three'])
        ->and(rtdb()->table('announcements')->orderBy('id')->offset(1)->limit(1)->pluck('title')->all())->toBe(['Two']);
});

it('compares dates chronologically', function () {
    rtdb()->table('announcements')->insert([
        ['title' => 'Old', 'body' => 'x', 'starts_at' => '2026-09-01 08:00:00'],
        ['title' => 'New', 'body' => 'x', 'starts_at' => '2026-10-03T08:00:00+08:00'],
    ]);

    expect(rtdb()->table('announcements')->where('starts_at', '>=', now()->setDate(2026, 10, 1))->pluck('title')->all())->toBe(['New'])
        ->and(rtdb()->table('announcements')->whereDate('starts_at', '2026-09-01')->pluck('title')->all())->toBe(['Old']);
});

it('computes aggregates in PHP', function () {
    rtdb()->table('walkin_items')->insert([
        ['sale_id' => 1, 'quantity' => 2, 'sheets_used' => 3, 'unit_price' => '1.10', 'amount' => '2.20'],
        ['sale_id' => 1, 'quantity' => 1, 'sheets_used' => 5, 'unit_price' => '0.10', 'amount' => '0.10'],
        ['sale_id' => 2, 'quantity' => 1, 'unit_price' => '5.00', 'amount' => '5.00'],
    ]);

    $items = fn () => rtdb()->table('walkin_items');

    expect($items()->count())->toBe(3)
        ->and($items()->where('sale_id', 1)->sum('amount'))->toBe(2.3)
        ->and($items()->sum('sheets_used'))->toBe(8)
        ->and($items()->max('sheets_used'))->toBe(5)
        ->and($items()->min('amount'))->toBe('0.10')
        ->and($items()->distinct()->count('sale_id'))->toBe(2)
        ->and($items()->where('sale_id', 9)->sum('amount'))->toBe(0)
        ->and($items()->where('sale_id', 1)->exists())->toBeTrue()
        ->and($items()->where('sale_id', 9)->exists())->toBeFalse();
});

it('updates and deletes matching rows', function () {
    rtdb()->table('locations')->insert([['name' => 'A', 'slug' => 'a'], ['name' => 'B', 'slug' => 'b']]);

    expect(rtdb()->table('locations')->where('slug', 'b')->update(['name' => 'Bee']))->toBe(1)
        ->and(rtdbRaw('locations/2/name'))->toBe('Bee')
        ->and(rtdb()->table('locations')->delete(1))->toBe(1)
        ->and(rtdbRaw('locations/1'))->toBeNull();
});

it('enforces unique columns', function () {
    rtdb()->table('locations')->insert(['name' => 'A', 'slug' => 'a']);

    rtdb()->table('locations')->insert(['name' => 'Again', 'slug' => 'a']);
})->throws(UniqueConstraintViolationException::class);

it('applies on-delete rules of referencing rows', function () {
    $user = rtdb()->table('users')->insertGetId(['name' => 'Ana', 'password' => 'x']);
    $admin = rtdb()->table('users')->insertGetId(['name' => 'Admin', 'password' => 'x', 'approved_by' => null]);
    rtdb()->table('users')->where('id', $user)->update(['approved_by' => $admin]);
    $location = rtdb()->table('locations')->insertGetId(['name' => 'A', 'slug' => 'a']);
    rtdb()->table('station_sessions')->insert(['user_id' => $user, 'location_id' => $location, 'opened_at' => now(), 'last_seen_at' => now()]);

    rtdb()->table('users')->delete($admin);

    expect(rtdbRaw("users/{$user}/approved_by"))->toBeNull();

    rtdb()->table('users')->delete($user);

    expect(rtdb()->table('station_sessions')->count())->toBe(0);

    rtdb()->table('station_sessions')->insert(['user_id' => $admin, 'location_id' => $location, 'opened_at' => now(), 'last_seen_at' => now()]);

    expect(fn () => rtdb()->table('locations')->delete($location))->toThrow(QueryException::class)
        ->and(rtdb()->table('locations')->count())->toBe(1);
});

it('commits a transaction as one batch and discards it on failure', function () {
    rtdb()->transaction(function () {
        rtdb()->table('locations')->insert(['name' => 'A', 'slug' => 'a']);

        expect(rtdb()->table('locations')->count())->toBe(1)
            ->and(app(BatchingDatabaseClient::class)->inner()->get('locations'))->toBeNull();
    });

    expect(rtdbRaw('locations/1/name'))->toBe('A');

    try {
        rtdb()->transaction(function () {
            rtdb()->table('locations')->where('id', 1)->update(['name' => 'Changed']);
            rtdb()->table('locations')->insert(['name' => 'B', 'slug' => 'b']);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        //
    }

    expect(rtdbRaw('locations/1/name'))->toBe('A')
        ->and(rtdb()->table('locations')->count())->toBe(1);
});

it('supports Eloquent models, relations and eager loading', function () {
    $location = RtdbTestLocation::create(['name' => 'Main', 'slug' => 'main']);
    $location->sessions()->create(['user_id' => 1, 'opened_at' => now(), 'last_seen_at' => now()]);
    $location->sessions()->create(['user_id' => 2, 'opened_at' => now(), 'last_seen_at' => now(), 'closed_at' => now()]);

    $loaded = RtdbTestLocation::with('sessions')->find($location->id);
    $session = RtdbTestSession::with('location')->whereNull('closed_at')->firstOrFail();

    expect($loaded->sessions)->toHaveCount(2)
        ->and($session->location->is($location))->toBeTrue()
        ->and($session->opened_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and(RtdbTestLocation::whereKey($location->id)->exists())->toBeTrue()
        ->and(RtdbTestSession::paginate(1)->total())->toBe(2);

    $session->update(['closed_at' => now()]);
    $location->sessions()->delete();
    $location->delete();

    expect(RtdbTestSession::whereNull('closed_at')->count())->toBe(0)
        ->and(RtdbTestLocation::count())->toBe(0);
});

it('rejects SQL-only features', function (Closure $query) {
    $query();
})->with([
    'joins' => fn () => rtdb()->table('sales')->join('print_jobs', 'print_jobs.id', '=', 'sales.print_job_id')->get(),
    'group by' => fn () => rtdb()->table('sales')->groupBy('user_id')->get(),
    'raw where' => fn () => rtdb()->table('sales')->whereRaw('1 = 1')->get(),
    'raw select' => fn () => rtdb()->table('sales')->selectRaw('count(*) as total')->get(),
    'raw statements' => fn () => rtdb()->statement('select 1'),
])->throws(UnsupportedQuery::class);
