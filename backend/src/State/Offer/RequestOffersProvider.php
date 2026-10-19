<?php

namespace App\State\Offer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Offer\OfferView;
use App\Entity\Intervention;
use App\Entity\ServiceRequest;
use App\Repository\RequestOfferRepository;
use App\Security\Voter\ServiceRequestVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * GET /api/requests/{id}/offers (§14.3) : offres que des professionnels ont acceptées pour la demande.
 *
 * @implements ProviderInterface<OfferView>
 */
final class RequestOffersProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly RequestOfferRepository $offers,
    ) {
    }

    /** @return list<OfferView> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $id = (string) ($uriVariables['requestId'] ?? '');
        $request = Uuid::isValid($id) ? $this->em->find(ServiceRequest::class, $id) : null;
        if (null === $request) {
            throw new NotFoundHttpException('Demande introuvable.');
        }
        if (!$this->security->isGranted(ServiceRequestVoter::VIEW, $request)) {
            throw new AccessDeniedHttpException();
        }

        $intervention = $this->em->getRepository(Intervention::class)->findOneBy(['request' => $request]);

        return array_map(
            static fn ($offer) => OfferView::forClient($offer, $intervention?->getOffer() === $offer ? $intervention : null),
            $this->offers->findVisibleToClient($request),
        );
    }
}
