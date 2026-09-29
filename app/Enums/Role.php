<?php

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Staff = 'staff';
    case Admin = 'admin';

    /**
     * Route name each role lands on after login.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Student => 'dashboard',
            self::Staff => 'staff.station',
            self::Admin => 'admin.dashboard',
        };
    }
}
