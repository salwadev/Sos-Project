<?php

namespace App\Intervention;

use App\Entity\Commission;
use App\Entity\Intervention;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Base et taux de la commission (§9.4) : assiette = total du devis accepté qui fait foi (frais de déplacement
 * compris s'ils y figurent), taux configurable par catégorie (config/services.yaml, app.commission.*).
 */
final class CommissionPolicy
{
    /** @param array<string, int> $rateByCategory */
    public function __construct(
        #[Autowire('%app.commission.default_rate_basis_points%')]
        private readonly int $defaultRate,
        #[Autowire('%app.commission.rate_basis_points_by_category%')]
        private readonly array $rateByCategory = [],
    ) {
    }

    public function rateFor(Intervention $intervention): int
    {
        return $this->rateByCategory[$intervention->getRequest()->getService()->getCategory()->getCode()] ?? $this->defaultRate;
    }

    public function commissionFor(Intervention $intervention): Commission
    {
        $quote = $intervention->getAcceptedQuote()
            ?? throw new InterventionConflict('Aucun devis accepté : la commission ne peut pas être calculée.');

        return new Commission($intervention, $quote->getTotalMillimes(), $this->rateFor($intervention));
    }
}
