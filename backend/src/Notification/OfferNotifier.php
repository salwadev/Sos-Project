<?php

namespace App\Notification;

use App\ApiResource\Offer\OfferView;
use App\Entity\Notification;
use App\Entity\RequestOffer;
use App\Enum\NotificationChannel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prépare la notification push d'un professionnel sollicité (§6.4, §12.5). La ligne Notification (PENDING)
 * est le point d'accroche de l'envoi push à venir ; elle est enregistrée avec la vague, sous le même verrou.
 *
 * Le payload se limite à ce dont le professionnel a besoin pour décider : catégorie, zone approximative,
 * urgence, frais de déplacement et délai de réponse. Jamais l'adresse, la position exacte, le repère ni la
 * description libre du client (§6.4, §16.7).
 */
final class OfferNotifier
{
    public const TYPE_OFFER_RECEIVED = 'offer.received';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function offerReceived(RequestOffer $offer, ?float $distanceMeters): Notification
    {
        $request = $offer->getRequest();
        $service = $request->getService();

        $notification = new Notification($offer->getProfessional()->getUser(), self::TYPE_OFFER_RECEIVED, NotificationChannel::Push, [
            'offerId' => $offer->getId()->toRfc4122(),
            'requestReference' => $request->getReference(),
            'categoryCode' => $service->getCategory()->getCode(),
            'serviceCode' => $service->getCode(),
            'serviceName' => $service->getNameFr(),
            'areaName' => $request->getArea()?->getName(),
            'approximateDistanceKm' => OfferView::approximateKm($distanceMeters),
            'urgency' => $request->getUrgency()->value,
            'travelFeeMillimes' => $offer->getTravelFeeMillimes(),
            'respondBefore' => $offer->getRespondBefore()->format(\DATE_ATOM),
        ]);
        $this->em->persist($notification);

        return $notification;
    }
}
