<?php

namespace App\ApiResource\Professional;

use Symfony\Component\Validator\Constraints as Assert;

/** Un métier proposé dans POST /api/professional/profile, avec ses frais connus à l'avance (§6.1, §9.1). */
final class ProfessionalServiceInput
{
    /** Code du service, ex. « plumbing » (voir GET /api/services). */
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    public ?string $serviceCode = null;

    /** Frais de déplacement en millimes (1 TND = 1000). */
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 1_000_000)]
    public ?int $travelFeeMillimes = null;

    /** Prix du diagnostic en millimes, s'il est facturé séparément. */
    #[Assert\Range(min: 0, max: 1_000_000)]
    public ?int $diagnosticFeeMillimes = null;
}
