<?php

namespace App\Realtime\Database;

use Illuminate\Database\Query\Grammars\Grammar;

/**
 * Only the parts of a query grammar Eloquent consults without running SQL:
 * dates are stored as ISO-8601 strings with their UTC offset.
 */
class QueryGrammar extends Grammar
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:sP';

    public function getDateFormat()
    {
        return self::DATE_FORMAT;
    }
}
