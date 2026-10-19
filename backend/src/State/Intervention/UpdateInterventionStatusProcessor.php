<?php

namespace App\State\Intervention;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Intervention\InterventionView;
use App\ApiResource\Intervention\UpdateInterventionStatusInput;
use App\Entity\Intervention;
use App\Enum\InterventionStatus;
use App\Intervention\InterventionLifecycle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * POST /api/interventions/{id}/status (§6.6, §14.4) : professionnel assigné seulement.
 * on_the_way → on_site → working (la demande passe IN_PROGRESS) → completion_declared.
 *
 * @implements ProcessorInterface<UpdateInterventionStatusInput, InterventionView>
 */
final class UpdateInterventionStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InterventionLifecycle $lifecycle,
        private readonly InterventionViews $views,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InterventionView
    {
        \assert($data instanceof UpdateInterventionStatusInput);
        $intervention = $this->em->find(Intervention::class, $uriVariables['id'] ?? null)
            ?? throw new NotFoundHttpException();

        $target = InterventionStatus::from((string) $data->status);
        if (null !== $data->estimatedArrivalMinutes && InterventionStatus::OnTheWay !== $target) {
            throw new UnprocessableEntityHttpException('estimatedArrivalMinutes accompagne seulement le statut « on_the_way ».');
        }

        $this->lifecycle->advance($intervention, $target, $this->views->currentUser(), $data->estimatedArrivalMinutes);

        return $this->views->for($intervention);
    }
}
