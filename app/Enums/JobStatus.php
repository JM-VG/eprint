<?php

namespace App\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Printing = 'printing';
    case Ready = 'ready';
    case Claimed = 'claimed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
