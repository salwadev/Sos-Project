<?php

namespace App\ApiResource\Offer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Intervention;
use App\Entity\RequestOffer;
use App\State\Offer\ProfessionalOffersProvider;
use App\State\Offer\RequestOffersProvider;

/**
 * Offre telle qu'exposée par l'API.
 * Côté professionnel, la demande n'est décrite que par sa zone et une distance arrondie :
 * l'adresse et la position exactes du client ne sont jamais incluses (§6.4).
 */
#[ApiResource(
    shortName: 'Offer',
    operations: [
        new GetCollection(
            uriTemplate: '/professional/requests',
            paginationEnabled: false,
            security: "is_granted('ROLE_PROFESSIONAL')",
            provider: ProfessionalOffersProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/requests/{requestId}/offers',
            uriVariables: ['requestId'],
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            provider: RequestOffersProvider::class,
        ),
    ],
)]
final class OfferView
{
    /**
     * @param array{id: string, reference: string, status: string, serviceCode: string, serviceName: string, categoryCode: string, urgency: string, description: string, areaName: string|null, approximateDistanceKm: float|null} $request
     * @param array{displayName: string, ratingAverage: float|null, ratingCount: int}|null                                                                                                                                       $professional
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly string $id,
        public readonly string $status,
        public readonly int $wave,
        public readonly int $travelFeeMillimes,
        public readonly \DateTimeImmutable $sentAt,
        public readonly \DateTimeImmutable $respondBefore,
        public readonly ?\DateTimeImmutable $reservedUntil,
        public readonly array $request,
        public readonly ?array $professional = null,
        public readonly ?string $interventionId = null,
    ) {
    }

    /** Vue du professionnel sollicité : zone approximative uniquement. */
    public static function forProfessional(RequestOffer $offer, ?float $distanceMeters): self
    {
        return self::build($offer, $distanceMeters, null, null);
    }

    /** Vue du client : identité publique et note du professionnel, conditions tarifaires (§5.5). */
    public static function forClient(RequestOffer $offer, ?Intervention $intervention = null): self
    {
        $professional = $offer->getProfessional();

        return self::build($offer, null, [
            'displayName' => $professional->getDisplayName(),
            'ratingAverage' => $professional->getRatingAverage(),
            'ratingCount' => $professional->getRatingCount(),
        ], $intervention?->getId()->toRfc4122());
    }

    /** Arrondi au demi-kilomètre supérieur : assez pour juger du trajet, trop grossier pour localiser le client. */
    public static function approximateKm(?float $meters): ?float
    {
        return null === $meters ? null : max(0.5, ceil($meters / 500) / 2);
    }

    /** @param array{displayName: string, ratingAverage: float|null, ratingCount: int}|null $professional */
    private static function build(RequestOffer $offer, ?float $distanceMeters, ?array $professional, ?string $interventionId): self
    {
        $request = $offer->getRequest();
        $service = $request->getService();

        return new self(
            $offer->getId()->toRfc4122(),
            $offer->getStatus()->value,
            $offer->getWave(),
            $offer->getTravelFeeMillimes(),
            $offer->getSentAt(),
            $offer->getRespondBefore(),
            $offer->getReservedUntil(),
            [
                'id' => $request->getId()->toRfc4122(),
                'reference' => $request->getReference(),
                'status' => $request->getStatus(),
                'serviceCode' => $service->getCode(),
                'serviceName' => $service->getNameFr(),
                'categoryCode' => $service->getCategory()->getCode(),
                'urgency' => $request->getUrgency()->value,
                'description' => $request->getDescription(),
                'areaName' => $request->getArea()?->getName(),
                'approximateDistanceKm' => self::approximateKm($distanceMeters),
            ],
            $professional,
            $interventionId,
        );
    }
}
