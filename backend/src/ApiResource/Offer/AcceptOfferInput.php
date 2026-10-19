<?php

namespace App\ApiResource\Offer;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /api/offers/{id}/accept. */
final class AcceptOfferInput
{
    /**
     * Professionnel seulement : frais de déplacement retenus, en millimes. Par défaut ceux annoncés
     * dans la sollicitation ; ils peuvent être baissés, jamais augmentés (frais connus à l'avance, §6.4, §9.1).
     * Ignoré lorsque c'est le client qui confirme.
     */
    #[Assert\PositiveOrZero]
    public ?int $travelFeeMillimes = null;
}
