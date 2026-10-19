<?php

namespace App\State\Intervention;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Intervention\InterventionView;
use App\Entity\Intervention;
use App\Security\Voter\InterventionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * GET /api/interventions/{id} : vue selon le rôle (adresse exacte révélée au professionnel assigné, §6.4).
 * GET /api/interventions : les interventions de l'utilisateur, comme client ou comme professionnel assigné.
 *
 * @implements ProviderInterface<InterventionView>
 */
final class InterventionProvider implements ProviderInterface
{
    private const COLLECTION_LIMIT = 50;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly InterventionViews $views,
    ) {
    }

    /** @return InterventionView|list<InterventionView> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InterventionView|array
    {
        if ($operation instanceof CollectionOperationInterface) {
            $user = $this->views->currentUser();
            $interventions = $this->em->createQuery(
                'SELECT i FROM '.Intervention::class.' i JOIN i.request r JOIN i.professional p
                 WHERE r.client = :user OR p.user = :user ORDER BY i.createdAt DESC',
            )->setParameter('user', $user)->setMaxResults(self::COLLECTION_LIMIT)->getResult();

            return array_map($this->views->for(...), $interventions);
        }

        $id = (string) ($uriVariables['id'] ?? '');
        $intervention = Uuid::isValid($id) ? $this->em->find(Intervention::class, $id) : null;
        if (null === $intervention) {
            throw new NotFoundHttpException('Intervention introuvable.');
        }
        if (!$this->security->isGranted(InterventionVoter::VIEW, $intervention)) {
            throw new AccessDeniedHttpException();
        }

        return $this->views->for($intervention);
    }
}
