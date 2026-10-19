<?php

namespace App\ApiResource\Professional;

use App\Entity\ProfessionalProfile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Corps de POST /api/professional/profile (§6.1, §14.4). Remplace l'ensemble du profil :
 * les métiers et zones absents de la requête ne sont plus proposés.
 */
final class ProfessionalProfileInput
{
    /** Nom affiché au client une fois l'offre réservée (§5.5). */
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 120)]
    public ?string $displayName = null;

    /** « individual » (indépendant) ou « company » (entreprise : extrait du registre requis à la vérification). */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [ProfessionalProfile::LEGAL_INDIVIDUAL, ProfessionalProfile::LEGAL_COMPANY])]
    public ?string $legalStatus = null;

    /** @var list<ProfessionalServiceInput> */
    #[Assert\Count(min: 1, max: 20)]
    #[Assert\Valid]
    public array $services = [];

    /**
     * Codes des zones couvertes (voir GET /api/service-areas). Une zone pas encore ouverte est acceptée :
     * c'est la couverture des professionnels qui permet de l'activer (§1.3).
     *
     * @var list<string>
     */
    #[Assert\Count(min: 1, max: 30)]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 40)])]
    #[Assert\Unique(message: 'Zone en double.')]
    public array $serviceAreaCodes = [];

    /** Point de départ habituel, pour le classement par distance (§8.2) ; jamais montré au client. */
    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90)]
    public ?float $baseLatitude = null;

    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180)]
    public ?float $baseLongitude = null;

    #[Assert\Callback]
    public function validateServices(ExecutionContextInterface $context): void
    {
        $seen = [];
        foreach ($this->services as $i => $service) {
            if (!$service instanceof ProfessionalServiceInput || null === $service->serviceCode) {
                continue;
            }
            if (isset($seen[$service->serviceCode])) {
                $context->buildViolation('Service en double.')->atPath(\sprintf('services[%d].serviceCode', $i))->addViolation();
            }
            $seen[$service->serviceCode] = true;
        }
    }
}
