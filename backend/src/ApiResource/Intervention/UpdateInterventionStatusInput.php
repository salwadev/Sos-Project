<?php

namespace App\ApiResource\Intervention;

use App\Enum\InterventionStatus;
use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /api/interventions/{id}/status (§6.6). */
final class UpdateInterventionStatusInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: InterventionStatus::PROFESSIONAL_STEPS)]
    public ?string $status = null;

    /** Avec « on_the_way » seulement : durée estimée du trajet, présentée au client comme une estimation (§5.6). */
    #[Assert\Range(min: 1, max: 240)]
    public ?int $estimatedArrivalMinutes = null;
}
