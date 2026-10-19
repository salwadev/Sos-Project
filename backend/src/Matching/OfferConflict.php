<?php

namespace App\Matching;

/** Action sur une offre devenue impossible (déjà réservée, expirée, délai dépassé…) : 409 Conflict (§16.3, §16.5). */
final class OfferConflict extends \DomainException
{
}
