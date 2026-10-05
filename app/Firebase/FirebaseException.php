<?php

namespace App\Firebase;

use RuntimeException;

class FirebaseException extends RuntimeException
{
    public static function forPath(string $path, string $reason): self
    {
        return new self("Firebase operation on [{$path}] failed: {$reason}");
    }
}
