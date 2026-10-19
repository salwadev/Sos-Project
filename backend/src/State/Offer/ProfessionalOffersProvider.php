<?php

namespace App\State\Offer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Offer\OfferView;
use App\Domain\Clock;
use App\Entity\User;
use App\Matching\CandidateFinder;
use App\Repository\RequestOfferRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /api/professional/requests : sollicitations encore ouvertes et réservations en cours du professionnel connecté.
 *
 * @implements ProviderInterface<OfferView>
 */
final class ProfessionalOffersProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestOfferRepository $offers,
        private readonly CandidateFinder $distances,
    ) {
    }

    /** @return list<OfferView> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        $professional = $user instanceof User ? $user->getProfessionalProfile() : null;
        if (null === $professional) {
            return [];
        }

        $views = [];
        foreach ($this->offers->findOpenForProfessional($professional, Clock::now()) as $offer) {
            $views[] = OfferView::forProfessional($offer, $this->distances->distanceMeters(
                $offer->getRequest()->getId()->toRfc4122(),
                $professional->getId()->toRfc4122(),
            ));
        }

        return $views;
    }
}
