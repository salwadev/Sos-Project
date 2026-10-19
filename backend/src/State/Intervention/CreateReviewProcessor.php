<?php

namespace App\State\Intervention;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Intervention\CreateReviewInput;
use App\ApiResource\Intervention\InterventionView;
use App\Entity\Intervention;
use App\Intervention\ReviewPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /api/interventions/{id}/reviews (§5.7, §14.3) : un avis du client propriétaire, une seule fois,
 * sur une intervention terminée et confirmée.
 *
 * @implements ProcessorInterface<CreateReviewInput, InterventionView>
 */
final class CreateReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReviewPublisher $reviews,
        private readonly InterventionViews $views,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InterventionView
    {
        \assert($data instanceof CreateReviewInput);
        $intervention = $this->em->find(Intervention::class, $uriVariables['id'] ?? null)
            ?? throw new NotFoundHttpException();

        $this->reviews->publish($intervention, $this->views->currentUser(), (int) $data->rating, $data->comment);

        return $this->views->for($intervention);
    }
}
