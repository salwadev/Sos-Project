<?php

namespace App\State\Intervention;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Intervention\DisputeInterventionInput;
use App\ApiResource\Intervention\InterventionView;
use App\Entity\Intervention;
use App\Intervention\InterventionLifecycle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /api/interventions/{id}/dispute (§5.7) : le client conteste pendant les travaux ou après la clôture ;
 * 409 si la demande n'est ni IN_PROGRESS ni COMPLETED.
 *
 * @implements ProcessorInterface<DisputeInterventionInput, InterventionView>
 */
final class DisputeInterventionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InterventionLifecycle $lifecycle,
        private readonly InterventionViews $views,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InterventionView
    {
        \assert($data instanceof DisputeInterventionInput);
        $intervention = $this->em->find(Intervention::class, $uriVariables['id'] ?? null)
            ?? throw new NotFoundHttpException();

        $this->lifecycle->dispute($intervention, $this->views->currentUser(), trim((string) $data->reason));

        return $this->views->for($intervention);
    }
}
