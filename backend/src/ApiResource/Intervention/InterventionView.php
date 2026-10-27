<?php

namespace App\ApiResource\Intervention;

use App\Domain\GeoPoint;
use App\Entity\Commission;
use App\Entity\Intervention;
use App\Entity\Payment;
use App\Entity\Quote;
use App\Entity\Review;
use App\Entity\User;
use App\Enum\ServiceRequestStatus;
use App\Enum\UserRole;

/**
 * Intervention telle qu'exposée par l'API, selon celui qui la consulte :
 * - l'adresse exacte n'est révélée au professionnel assigné qu'après confirmation par le client, et seulement tant
 *   que l'intervention est en cours (§6.4) ; le client propriétaire et l'administration la voient toujours ;
 * - la commission n'est visible que du professionnel et de l'administration (§6.7) ;
 * - le paiement en espèces (déclaré à la clôture, puis confirmé par le professionnel) est visible de tous ;
 * - les heures sont en UTC ; estimatedArrivalAt est une estimation et doit être affichée comme telle (§5.6).
 */
final class InterventionView
{
    /**
     * @param array{id: string, reference: string, status: string, serviceCode: string, serviceName: string, urgency: string, description: string}                $request
     * @param array{displayName: string, ratingAverage: float|null, ratingCount: int}                                                                              $professional
     * @param array{addressLine: string, landmark: string|null, latitude: float|null, longitude: float|null}|null                                                 $address
     * @param list<array<string, mixed>>                                                                                                                           $quotes
     * @param array<string, mixed>|null                                                                                                                            $acceptedQuote
     * @param array<string, mixed>|null                                                                                                                            $pendingQuote
     * @param array{baseMillimes: int, rateBasisPoints: int, amountMillimes: int, currency: string, status: string}|null                                           $commission
     * @param array{method: string, status: string, amountMillimes: int, currency: string}|null                                                                 $payment
     * @param array{rating: int, comment: string|null, createdAt: \DateTimeImmutable}|null                                                                         $review
     */
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly array $request,
        public readonly array $professional,
        public readonly int $travelFeeMillimes,
        public readonly ?array $address,
        public readonly ?\DateTimeImmutable $estimatedArrivalAt,
        public readonly ?\DateTimeImmutable $onTheWayAt,
        public readonly ?\DateTimeImmutable $arrivedAt,
        public readonly ?\DateTimeImmutable $workStartedAt,
        public readonly ?\DateTimeImmutable $completionDeclaredAt,
        public readonly ?\DateTimeImmutable $completedAt,
        public readonly array $quotes,
        public readonly ?array $acceptedQuote,
        public readonly ?array $pendingQuote,
        public readonly ?array $commission,
        public readonly ?array $payment,
        public readonly ?array $review,
    ) {
    }

    public static function for(Intervention $intervention, User $viewer, ?Commission $commission, ?Payment $payment, ?Review $review): self
    {
        $request = $intervention->getRequest();
        $professional = $intervention->getProfessional();
        $service = $request->getService();
        $isAdmin = $viewer->hasRole(UserRole::Admin);
        $isProfessional = $intervention->isAssignedTo($viewer);
        $active = \in_array($request->getStatusEnum(), [ServiceRequestStatus::Accepted, ServiceRequestStatus::InProgress], true);

        $address = null;
        if ($isAdmin || $request->isOwnedBy($viewer) || ($isProfessional && $active)) {
            [$latitude, $longitude] = GeoPoint::fromEwkt($request->getLocation()) ?? [null, null];
            $address = [
                'addressLine' => $request->getAddressLine(),
                'landmark' => $request->getLandmark(),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        return new self(
            $intervention->getId()->toRfc4122(),
            $intervention->getStatus()->value,
            [
                'id' => $request->getId()->toRfc4122(),
                'reference' => $request->getReference(),
                'status' => $request->getStatus(),
                'serviceCode' => $service->getCode(),
                'serviceName' => $service->getNameFr(),
                'urgency' => $request->getUrgency()->value,
                'description' => $request->getDescription(),
            ],
            [
                'displayName' => $professional->getDisplayName(),
                'ratingAverage' => $professional->getRatingAverage(),
                'ratingCount' => $professional->getRatingCount(),
            ],
            $intervention->getOffer()->getTravelFeeMillimes(),
            $address,
            $intervention->getEstimatedArrivalAt(),
            $intervention->getOnTheWayAt(),
            $intervention->getArrivedAt(),
            $intervention->getWorkStartedAt(),
            $intervention->getCompletionDeclaredAt(),
            $intervention->getCompletedAt(),
            array_values(array_map(self::quote(...), $intervention->getQuotes()->toArray())),
            self::quote($intervention->getAcceptedQuote()),
            self::quote($intervention->getPendingQuote()),
            null !== $commission && ($isProfessional || $isAdmin) ? [
                'baseMillimes' => $commission->getBaseMillimes(),
                'rateBasisPoints' => $commission->getRateBasisPoints(),
                'amountMillimes' => $commission->getAmountMillimes(),
                'currency' => $commission->getCurrency(),
                'status' => $commission->getStatus()->value,
            ] : null,
            null === $payment ? null : [
                'method' => $payment->getMethod()->value,
                'status' => $payment->getStatus()->value,
                'amountMillimes' => $payment->getGrossMillimes(),
                'currency' => $payment->getCurrency(),
            ],
            null === $review ? null : [
                'rating' => $review->getRating(),
                'comment' => $review->getComment(),
                'createdAt' => $review->getCreatedAt(),
            ],
        );
    }

    /** @return array<string, mixed>|null */
    private static function quote(?Quote $quote): ?array
    {
        return null === $quote ? null : [
            'id' => $quote->getId()->toRfc4122(),
            'version' => $quote->getVersion(),
            'status' => $quote->getStatus()->value,
            'lines' => $quote->getLines(),
            'totalMillimes' => $quote->getTotalMillimes(),
            'currency' => $quote->getCurrency(),
            'explanation' => $quote->getExplanation(),
            'proposedAt' => $quote->getProposedAt(),
            'decidedAt' => $quote->getDecidedAt(),
        ];
    }
}
