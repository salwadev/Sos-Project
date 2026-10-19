<?php

namespace App\Enum;

/** Sollicitation d'un professionnel pour une demande (§6.4, §6.5). */
enum OfferStatus: string
{
    case Sent = 'sent';
    case Reserved = 'reserved';
    case Declined = 'declined';
    case Expired = 'expired';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
