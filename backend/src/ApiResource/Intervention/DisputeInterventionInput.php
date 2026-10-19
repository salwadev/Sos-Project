<?php

namespace App\ApiResource\Intervention;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /api/interventions/{id}/dispute : motif de la contestation, conservé avec son horodatage (§5.7). */
final class DisputeInterventionInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 1000)]
    public ?string $reason = null;
}
