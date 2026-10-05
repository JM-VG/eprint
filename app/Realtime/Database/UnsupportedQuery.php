<?php

namespace App\Realtime\Database;

use LogicException;

/**
 * Thrown for SQL-only features (joins, raw expressions, GROUP BY, ...) that
 * the Realtime Database connection deliberately does not emulate.
 */
class UnsupportedQuery extends LogicException
{
    public static function because(string $reason): self
    {
        return new self("Unsupported Realtime Database query: {$reason}.");
    }
}
