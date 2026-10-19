<?php

namespace App\Enum;

/** Mode de paiement (§9.3) : direct au professionnel pour le lancement. */
enum PaymentMethod: string
{
    case CashDirect = 'cash_direct';
    case Provider = 'provider';
}
