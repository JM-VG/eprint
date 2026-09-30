<?php

namespace App\Services;

use App\Models\PrintJob;
use Closure;
use RuntimeException;

/**
 * Claim codes look like EP-7KQ2: four characters without look-alikes (no I, L, O, 0, 1).
 */
class ClaimCodeGenerator
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const MAX_ATTEMPTS = 20;

    /**
     * @param  (Closure(): string)|null  $pickChar  character source, injectable for tests
     */
    public function __construct(private ?Closure $pickChar = null)
    {
        $this->pickChar ??= fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
    }

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = 'EP-'.implode('', array_map(fn () => ($this->pickChar)(), range(1, 4)));

            if (! PrintJob::where('claim_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate a unique claim code.');
    }
}
