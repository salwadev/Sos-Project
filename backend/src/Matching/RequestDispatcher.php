<?php

namespace App\Matching;

use App\Domain\Clock;
use App\Domain\Request\RequestTransition;
use App\Domain\Request\ServiceRequestStateMachine;
use App\Entity\ProfessionalProfile;
use App\Entity\RequestOffer;
use App\Entity\ServiceRequest;
use App\Enum\OfferStatus;
use App\Enum\ServiceRequestStatus;
use App\Notification\OfferNotifier;
use App\Repository\RequestOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Diffusion par vagues (§5.8, §8.3) : sollicite un petit groupe de professionnels éligibles, attend leur
 * réponse, puis passe aux suivants. Quand plus personne n'est éligible, la demande expire et n'est jamais
 * présentée comme prise en charge. Chaque passage s'exécute sous verrou de la demande (RequestLock).
 * Chaque professionnel sollicité reçoit une Notification push (§6.4), enregistrée avec son offre.
 *
 * Appelé juste après la soumission (message DispatchServiceRequest), par le passage périodique
 * (MatchingTick, commande app:matching:dispatch) et après un refus.
 */
final class RequestDispatcher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestLock $lock,
        private readonly RequestOfferRepository $offers,
        private readonly ServiceRequestStateMachine $stateMachine,
        private readonly CandidateFinder $candidates,
        private readonly ProfessionalEligibility $eligibility,
        private readonly MatchingSettings $settings,
        private readonly OfferNotifier $notifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatch(ServiceRequest $request): DispatchOutcome
    {
        return $this->lock->run($request, fn (ServiceRequest $request) => $this->advance($request, Clock::now()));
    }

    private function advance(ServiceRequest $request, \DateTimeImmutable $now): DispatchOutcome
    {
        $offers = $this->offers->findForRequest($request);

        if (ServiceRequestStatus::Offered === $request->getStatusEnum()) {
            $reserved = $this->firstWithStatus($offers, OfferStatus::Reserved);
            if (null !== $reserved && $reserved->getReservedUntil() > $now) {
                return DispatchOutcome::Reserved;
            }
            $reserved?->expire();
            $this->stateMachine->apply($request, RequestTransition::ReopenSearch, null, 'Réservation expirée sans confirmation du client');
        }

        if (ServiceRequestStatus::Submitted === $request->getStatusEnum()) {
            $this->stateMachine->apply($request, RequestTransition::StartSearch, null);
        }

        if (ServiceRequestStatus::Searching !== $request->getStatusEnum()) {
            return DispatchOutcome::Idle;
        }

        $waiting = false;
        foreach ($offers as $offer) {
            if (OfferStatus::Sent !== $offer->getStatus()) {
                continue;
            }
            if ($offer->getRespondBefore() <= $now) {
                $offer->expire();
            } else {
                $waiting = true;
            }
        }
        if ($waiting) {
            return DispatchOutcome::Waiting;
        }

        $selected = $this->nextWave($request, $offers, $now);
        if ([] === $selected) {
            $this->stateMachine->apply($request, RequestTransition::Expire, null, 'Aucun professionnel disponible');
            $this->logger->info('Matching : demande {reference} expirée, aucun professionnel éligible restant.', [
                'reference' => $request->getReference(),
                'correlationId' => $request->getCorrelationId()->toRfc4122(),
            ]);

            return DispatchOutcome::Expired;
        }

        $wave = 1 + array_reduce($offers, static fn (int $max, RequestOffer $o) => max($max, $o->getWave()), 0);
        $deadline = $this->settings->responseDeadline($now);
        foreach ($selected as [$professional, $distanceMeters]) {
            $offering = $professional->findOffering($request->getService());
            \assert(null !== $offering);
            $offer = new RequestOffer($request, $professional, $wave, $offering->getTravelFeeMillimes(), $now, $deadline);
            $request->getOffers()->add($offer);
            $this->em->persist($offer);
            $this->notifier->offerReceived($offer, $distanceMeters);
        }
        $this->logger->info('Matching : vague {wave} de la demande {reference} envoyée à {count} professionnel(s).', [
            'wave' => $wave,
            'reference' => $request->getReference(),
            'count' => \count($selected),
            'correlationId' => $request->getCorrelationId()->toRfc4122(),
        ]);

        return DispatchOutcome::WaveSent;
    }

    /**
     * @param list<RequestOffer> $offers
     *
     * @return list<array{ProfessionalProfile, float|null}> professionnels retenus et distance à la demande (mètres)
     */
    private function nextWave(ServiceRequest $request, array $offers, \DateTimeImmutable $now): array
    {
        $alreadySolicited = array_map(static fn (RequestOffer $o) => $o->getProfessional()->getId()->toRfc4122(), $offers);
        $selected = [];
        foreach ($this->candidates->rankedCandidates($request, $now, $alreadySolicited) as $candidate) {
            $professional = $this->em->find(ProfessionalProfile::class, $candidate['id']);
            if (null === $professional) {
                continue;
            }
            $reason = $this->eligibility->exclusionReason($professional, $request, $now);
            if (null !== $reason) {
                $this->logger->debug('Matching : professionnel {professional} écarté ({reason}).', [
                    'professional' => $candidate['id'],
                    'reason' => $reason,
                ]);
                continue;
            }
            $selected[] = [$professional, $candidate['distanceMeters']];
            if (\count($selected) >= $this->settings->waveSize) {
                break;
            }
        }

        return $selected;
    }

    /** @param list<RequestOffer> $offers */
    private function firstWithStatus(array $offers, OfferStatus $status): ?RequestOffer
    {
        foreach ($offers as $offer) {
            if ($status === $offer->getStatus()) {
                return $offer;
            }
        }

        return null;
    }
}
