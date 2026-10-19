<?php

namespace App\Enum;

/** Vérification d'un professionnel (§6.2, §7.2). */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
