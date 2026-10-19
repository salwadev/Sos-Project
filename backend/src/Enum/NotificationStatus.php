<?php

namespace App\Enum;

/** Envoi d'une notification avec relances (§12.5). */
enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
