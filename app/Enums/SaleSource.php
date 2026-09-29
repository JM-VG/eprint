<?php

namespace App\Enums;

enum SaleSource: string
{
    case Online = 'online';
    case Walkin = 'walkin';
    case StaffJob = 'staff_job';
}
