<?php

namespace App\ApiResource\Professional;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Entity\Availability;
use App\State\Professional\AvailabilityProvider;
use App\State\Professional\UpdateAvailabilityProcessor;

/**
 * Bouton Disponible / Indisponible du professionnel connecté (§6.3, §14.4).
 * La disponibilité expire à availableUntil et doit alors être reconfirmée.
 */
#[ApiResource(
    shortName: 'ProfessionalAvailability',
    operations: [
        new Get(uriTemplate: '/professional/availability'),
        new Patch(
            uriTemplate: '/professional/availability',
            input: UpdateAvailabilityInput::class,
            processor: UpdateAvailabilityProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_PROFESSIONAL')",
    provider: AvailabilityProvider::class,
)]
final class AvailabilityStatus
{
    public function __construct(
        public readonly bool $available,
        public readonly ?\DateTimeImmutable $availableUntil,
        public readonly ?\DateTimeImmutable $lastConfirmedAt,
    ) {
    }

    public static function of(?Availability $availability, \DateTimeImmutable $now): self
    {
        return new self(
            $availability?->isAvailableAt($now) ?? false,
            $availability?->isAvailableAt($now) ? $availability->getAvailableUntil() : null,
            $availability?->getLastConfirmedAt(),
        );
    }
}
