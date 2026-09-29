<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Disabled = 'disabled';

    /**
     * Message shown on login for accounts that may not sign in.
     */
    public function loginMessage(?string $rejectionReason = null): ?string
    {
        return match ($this) {
            self::Approved => null,
            self::Pending => 'Your account is still waiting for approval. Please check back later or visit the printing room.',
            self::Rejected => 'Your sign up was rejected'.($rejectionReason ? ": {$rejectionReason}" : '').'. Visit the printing room if you think this is a mistake.',
            self::Disabled => 'Your account has been disabled. Please visit the printing room.',
        };
    }
}
