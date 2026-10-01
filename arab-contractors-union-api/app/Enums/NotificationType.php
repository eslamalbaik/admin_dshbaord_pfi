<?php

namespace App\Enums;

enum NotificationType: string
{
    case ANNOUNCEMENT = 'announcement';
    case PAYMENT_REMINDER = 'payment_reminder';
    case EXPIRY_REMINDER = 'expiry_reminder';
}
