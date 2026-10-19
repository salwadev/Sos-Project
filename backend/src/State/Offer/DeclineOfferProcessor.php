<?php

namespace App\State\Offer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Offer\OfferView;
use App\Entity\RequestOffer;
use App\Entity\User;
use App\Matching\OfferResponder;
use App\Security\Voter\RequestOfferVoter;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * POST /api/offers/{id}/decline :
 * - par le professionnel sollicité → il décline, la vague suivante part si plus personne n'est en attente ;
 * - par le client propriétaire → il refuse l'offre réservée, la recherche reprend (OFFERED → SEARCHING).
 *
 * @implements ProcessorInterface<RequestOffer, OfferView>
 */
final class DeclineOfferProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OfferResponder $responder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OfferView
    {
        \assert($data instanceof RequestOffer);
        $actor = $this->security->getUser();
        \assert($actor instanceof User);

        if (RequestOfferVoter::isSolicitedProfessional($data, $actor)) {
            return OfferView::forProfessional($this->responder->decline($data, $actor), null);
        }

        return OfferView::forClient($this->responder->reject($data, $actor));
    }
}
