<?php

namespace App\Enum;

/** Suivi d'un mouvement financier (§9.6). */
enum PaymentStatus: string
{
    case Declared = 'declared';
    case Confirmed = 'confirmed';
    case Refunded = 'refunded';
}
