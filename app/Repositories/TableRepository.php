<?php

namespace App\Repositories;

use App\Firebase\DatabaseClient;

/**
 * Repository for a table without a dedicated class (notifications,
 * password reset tokens, ...).
 */
class TableRepository extends Repository
{
    public function __construct(DatabaseClient $database, private string $tableName)
    {
        parent::__construct($database);
    }

    public function table(): string
    {
        return $this->tableName;
    }
}
