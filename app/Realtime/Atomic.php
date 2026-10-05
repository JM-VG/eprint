<?php

namespace App\Realtime;

use App\Realtime\Database\RealtimeConnection;
use Illuminate\Support\Facades\DB;

/**
 * The Realtime Database replacement for DB::transaction() + lockForUpdate().
 *
 * Every write the callback makes is buffered and committed as one atomic
 * multi-path update (or discarded if it throws). The named locks are held
 * from before the first read until after the commit, so two requests
 * cannot interleave on the same record. There is no cross-record
 * isolation beyond that; `firebase:reconcile` repairs derived totals.
 */
class Atomic
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  list<string>  $locks  e.g. "print_jobs/12"
     * @return T
     */
    public static function run(callable $callback, array $locks = []): mixed
    {
        /** @var RealtimeConnection $connection */
        $connection = DB::connection('rtdb');

        return app(Locks::class)->hold($locks, fn () => $connection->atomically($callback));
    }
}
