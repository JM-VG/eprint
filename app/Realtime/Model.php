<?php

namespace App\Realtime;

use App\Realtime\Concerns\UsesRealtimeDatabase;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Base class for models stored in the Firebase Realtime Database.
 *
 * Records live at "/{table}/{id}" with integer ids from "/counters/{table}".
 * The full Eloquent API is available (attributes and casts, relations,
 * eager loading, factories, pagination); queries are evaluated by
 * {@see Database\QueryBuilder}, which rejects SQL-only features.
 */
abstract class Model extends EloquentModel
{
    use UsesRealtimeDatabase;
}
