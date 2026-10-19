<?php

namespace App\ApiResource\Professional;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Corps de PATCH /api/professional/availability (application/merge-patch+json). */
final class UpdateAvailabilityInput
{
    #[Assert\NotNull]
    public ?bool $available = null;

    /** Durée de la disponibilité en minutes (défaut et maximum : config/services.yaml, app.availability.*). */
    #[Assert\Positive]
    public ?int $durationMinutes = null;

    /** Position actuelle, facultative : affine le classement par distance (§8.2), jamais montrée au client. */
    #[Assert\Range(min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $longitude = null;

    #[Assert\Callback]
    public function validateCoordinates(ExecutionContextInterface $context): void
    {
        if ((null === $this->latitude) !== (null === $this->longitude)) {
            $context->buildViolation('Latitude et longitude vont ensemble.')
                ->atPath(null === $this->latitude ? 'latitude' : 'longitude')
                ->addViolation();
        }
    }
}
