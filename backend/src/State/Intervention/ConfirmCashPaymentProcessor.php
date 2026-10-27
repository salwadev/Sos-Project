<?php

namespace App\State\Intervention;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Intervention\InterventionView;
use App\Entity\Intervention;
use App\Intervention\InterventionLifecycle;

/**
 * POST /api/interventions/{id}/payment/confirm-cash (§9.3, §9.6) : le professionnel assigné confirme avoir reçu
 * le paiement en espèces déclaré à la clôture ; une seule fois.
 *
 * @implements ProcessorInterface<Intervention, InterventionView>
 */
final class ConfirmCashPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly InterventionLifecycle $lifecycle,
        private readonly InterventionViews $views,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InterventionView
    {
        \assert($data instanceof Intervention);
        $this->lifecycle->confirmCashReceived($data, $this->views->currentUser());

        return $this->views->for($data);
    }
}
