<?php

namespace App\Enums;

enum JobName: string
{
    case GRACE_PERIOD = 'grace_period';
    case RENEWAL = 'renewal';
    case ANNOUNCEMENT = 'announcement';
}
