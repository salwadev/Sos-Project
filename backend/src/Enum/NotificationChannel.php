<?php

namespace App\Enum;

/** Canal d'envoi (§12.5). */
enum NotificationChannel: string
{
    case Push = 'push';
    case Sms = 'sms';
}
