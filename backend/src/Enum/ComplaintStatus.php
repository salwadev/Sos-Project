<?php

namespace App\Enum;

/** Traitement d'une réclamation (§7.4). */
enum ComplaintStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}
