<?php

namespace App\State\Intervention;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Intervention\InterventionView;
use App\Entity\Intervention;
use App\Intervention\InterventionLifecycle;

/**
 * POST /api/interventions/{id}/confirm-completion (§5.7, §14.3) : le client confirme la fin déclarée par le
 * professionnel ; la demande passe COMPLETED et la commission est calculée une seule fois (§16.1).
 *
 * @implements ProcessorInterface<Intervention, InterventionView>
 */
final class ConfirmCompletionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly InterventionLifecycle $lifecycle,
        private readonly InterventionViews $views,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InterventionView
    {
        \assert($data instanceof Intervention);
        $this->lifecycle->confirmCompletion($data, $this->views->currentUser());

        return $this->views->for($data);
    }
}
