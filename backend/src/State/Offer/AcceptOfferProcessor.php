<?php

namespace App\State\Offer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Offer\AcceptOfferInput;
use App\ApiResource\Offer\OfferView;
use App\Entity\RequestOffer;
use App\Entity\User;
use App\Matching\CandidateFinder;
use App\Matching\OfferResponder;
use App\Security\Voter\RequestOfferVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * POST /api/offers/{id}/accept :
 * - par le professionnel sollicité → réservation exclusive de la demande (§6.5) ;
 * - par le client propriétaire → confirmation du professionnel et création de l'intervention (§5.5).
 * Conflits (déjà réservée, expirée…) : 409 avec un message explicite (§16.3, §16.5).
 *
 * @implements ProcessorInterface<AcceptOfferInput, OfferView>
 */
final class AcceptOfferProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly OfferResponder $responder,
        private readonly CandidateFinder $distances,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OfferView
    {
        \assert($data instanceof AcceptOfferInput);
        $actor = $this->security->getUser();
        \assert($actor instanceof User);

        $offer = $this->em->find(RequestOffer::class, $uriVariables['id'] ?? null)
            ?? throw new NotFoundHttpException();

        if (RequestOfferVoter::isSolicitedProfessional($offer, $actor)) {
            if (null !== $data->travelFeeMillimes && $data->travelFeeMillimes > $offer->getTravelFeeMillimes()) {
                throw new UnprocessableEntityHttpException('Les frais de déplacement ne peuvent pas dépasser ceux annoncés.');
            }
            $this->responder->reserve($offer, $actor, $data->travelFeeMillimes);

            return OfferView::forProfessional($offer, $this->distances->distanceMeters(
                $offer->getRequest()->getId()->toRfc4122(),
                $offer->getProfessional()->getId()->toRfc4122(),
            ));
        }

        $intervention = $this->responder->confirm($offer, $actor);

        return OfferView::forClient($offer, $intervention);
    }
}
