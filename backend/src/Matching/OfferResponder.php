<?php

namespace App\Matching;

use App\Audit\AuditLogger;
use App\Domain\Clock;
use App\Domain\Request\RequestTransition;
use App\Domain\Request\ServiceRequestStateMachine;
use App\Entity\Intervention;
use App\Entity\RequestOffer;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\OfferStatus;
use App\Enum\ServiceRequestStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Réponses à une offre (§5.5, §6.5) :
 * - le professionnel sollicité accepte (réservation exclusive, SEARCHING → OFFERED) ou refuse ;
 * - le client confirme (OFFERED → ACCEPTED, création de l'intervention) ou refuse (OFFERED → SEARCHING).
 *
 * Toute décision s'exécute sous RequestLock et revérifie l'état relu en base : sur deux acceptations
 * simultanées, la seconde obtient une OfferConflict explicite (409) au lieu d'une double réservation (§16.3).
 */
final class OfferResponder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestLock $lock,
        private readonly ServiceRequestStateMachine $stateMachine,
        private readonly ProfessionalEligibility $eligibility,
        private readonly MatchingSettings $settings,
        private readonly RequestDispatcher $dispatcher,
        private readonly AuditLogger $audit,
    ) {
    }

    /** Le professionnel accepte : la demande lui est réservée le temps que le client confirme. */
    public function reserve(RequestOffer $offer, User $actor, ?int $travelFeeMillimes = null): RequestOffer
    {
        $request = $offer->getRequest();
        $this->lock->run($request, function (ServiceRequest $request) use ($offer, $actor, $travelFeeMillimes): void {
            $this->em->refresh($offer);
            $now = Clock::now();

            $this->assertRequestStillOpen($request);
            if (ServiceRequestStatus::Searching !== $request->getStatusEnum()) {
                throw new OfferConflict(OfferStatus::Reserved === $offer->getStatus()
                    ? 'Vous avez déjà accepté cette demande.'
                    : 'Demande déjà réservée par un autre professionnel.');
            }
            $this->assertOfferStatus($offer, OfferStatus::Sent);
            if ($offer->getRespondBefore() <= $now) {
                throw new OfferConflict('Le délai de réponse à cette demande est dépassé.');
            }
            $reason = $this->eligibility->exclusionReason($offer->getProfessional(), $request, $now);
            if (null !== $reason) {
                throw new OfferConflict(\sprintf('Vous ne pouvez plus accepter cette demande : %s.', $reason));
            }

            $offer->reserve($now, $this->settings->reservationEnd($now), $travelFeeMillimes ?? $offer->getTravelFeeMillimes());
            $this->stateMachine->apply($request, RequestTransition::ReceiveOffer, $actor);
        });

        $this->audit->record('offer.reserved', $actor, 'RequestOffer', $offer->getId()->toRfc4122(), [
            'request' => $request->getReference(),
            'travelFeeMillimes' => $offer->getTravelFeeMillimes(),
        ]);

        return $offer;
    }

    /** Le professionnel décline : si plus personne n'est en attente, la vague suivante part aussitôt. */
    public function decline(RequestOffer $offer, User $actor): RequestOffer
    {
        $request = $offer->getRequest();
        $this->lock->run($request, function () use ($offer): void {
            $this->em->refresh($offer);
            $this->assertOfferStatus($offer, OfferStatus::Sent);
            $offer->decline(Clock::now());
        });

        $this->dispatcher->dispatch($request);

        return $offer;
    }

    /** Le client confirme le professionnel et ses conditions tarifaires (§5.5) : l'intervention est créée. */
    public function confirm(RequestOffer $offer, User $client): Intervention
    {
        $request = $offer->getRequest();
        $intervention = $this->lock->run($request, function (ServiceRequest $request) use ($offer, $client): Intervention {
            $this->em->refresh($offer);
            $this->assertRequestStillOpen($request);
            $this->assertReservationPending($request, $offer);

            $offer->confirmByClient();
            $this->stateMachine->apply($request, RequestTransition::AcceptOffer, $client);
            $intervention = new Intervention($request, $offer);
            $this->em->persist($intervention);

            return $intervention;
        });

        $this->audit->record('offer.confirmed', $client, 'RequestOffer', $offer->getId()->toRfc4122(), [
            'request' => $request->getReference(),
            'intervention' => $intervention->getId()->toRfc4122(),
            'travelFeeMillimes' => $offer->getTravelFeeMillimes(),
        ]);

        return $intervention;
    }

    /** Le client refuse le professionnel proposé : la recherche reprend avec la vague suivante (§5.8). */
    public function reject(RequestOffer $offer, User $client): RequestOffer
    {
        $request = $offer->getRequest();
        $this->lock->run($request, function (ServiceRequest $request) use ($offer, $client): void {
            $this->em->refresh($offer);
            $this->assertRequestStillOpen($request);
            $this->assertReservationPending($request, $offer);

            $offer->rejectByClient();
            $this->stateMachine->apply($request, RequestTransition::ReopenSearch, $client, 'Professionnel refusé par le client');
        });

        $this->audit->record('offer.rejected', $client, 'RequestOffer', $offer->getId()->toRfc4122(), [
            'request' => $request->getReference(),
        ]);
        $this->dispatcher->dispatch($request);

        return $offer;
    }

    private function assertRequestStillOpen(ServiceRequest $request): void
    {
        $message = match ($request->getStatusEnum()) {
            ServiceRequestStatus::Expired => 'Cette demande a expiré : elle ne peut plus être acceptée.',
            ServiceRequestStatus::Cancelled => 'Cette demande a été annulée.',
            ServiceRequestStatus::Accepted, ServiceRequestStatus::InProgress,
            ServiceRequestStatus::Completed, ServiceRequestStatus::Disputed => 'Cette demande est déjà attribuée.',
            default => null,
        };
        if (null !== $message) {
            throw new OfferConflict($message);
        }
    }

    private function assertReservationPending(ServiceRequest $request, RequestOffer $offer): void
    {
        if (ServiceRequestStatus::Offered !== $request->getStatusEnum() || OfferStatus::Reserved !== $offer->getStatus()) {
            throw new OfferConflict('Ce professionnel n\'a pas (ou plus) de réservation en cours sur cette demande.');
        }
        if ($offer->getReservedUntil() <= Clock::now()) {
            throw new OfferConflict('La réservation de ce professionnel a expiré ; la recherche va reprendre.');
        }
    }

    private function assertOfferStatus(RequestOffer $offer, OfferStatus $expected): void
    {
        if ($expected !== $offer->getStatus()) {
            throw new OfferConflict(match ($offer->getStatus()) {
                OfferStatus::Expired => 'Cette sollicitation a expiré.',
                OfferStatus::Declined => 'Vous avez déjà décliné cette demande.',
                default => \sprintf('Sollicitation en statut « %s » : action impossible.', $offer->getStatus()->value),
            });
        }
    }
}
