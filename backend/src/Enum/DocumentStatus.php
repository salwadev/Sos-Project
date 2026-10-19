<?php

namespace App\Enum;

/** Contrôle d'un justificatif. */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
