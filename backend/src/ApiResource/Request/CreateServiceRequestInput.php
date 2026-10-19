<?php

namespace App\ApiResource\Request;

use App\Enum\Urgency;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Corps de POST /api/requests (§5.3, §14.3). */
final class CreateServiceRequestInput
{
    /** Code du service, ex. « plumbing » (voir GET /api/services). */
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    public ?string $serviceCode = null;

    /** Valeur de Urgency : « asap » ou « today ». */
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [Urgency::class, 'values'])]
    public ?string $urgency = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $addressLine = null;

    #[Assert\Length(max: 255)]
    public ?string $landmark = null;

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
