<?php

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Staff = 'staff';
    case Admin = 'admin';
}
