<?php

namespace App\Exceptions;

use App\Enums\JobStatus;
use DomainException;

class InvalidJobTransition extends DomainException
{
    public static function between(JobStatus $from, JobStatus $to): self
    {
        return new self("A {$from->value} job cannot be marked {$to->value}.");
    }
}
