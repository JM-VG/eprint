<?php

namespace App\Enums;

enum CloseReason: string
{
    case Manual = 'manual';
    case Logout = 'logout';
    case Switch = 'switch';
    case Timeout = 'timeout';
}
