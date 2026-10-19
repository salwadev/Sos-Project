<?php

namespace App\Matching;

use App\Entity\Intervention;
use App\Entity\ProfessionalProfile;
use App\Entity\RequestOffer;
use App\Entity\ServiceRequest;
use App\Enum\DocumentType;
use App\Enum\InterventionStatus;
use App\Enum\OfferStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Critères d'éligibilité d'un professionnel pour une demande (§8.1, §16.2).
 * Source de vérité : appliquée à chaque candidat présélectionné par CandidateFinder
 * et revérifiée au moment où le professionnel accepte.
 */
final class ProfessionalEligibility
{
    /** Interventions qui occupent encore le professionnel. */
    public const BUSY_INTERVENTION_STATUSES = [
        InterventionStatus::Confirmed,
        InterventionStatus::OnTheWay,
        InterventionStatus::OnSite,
        InterventionStatus::Working,
        InterventionStatus::CompletionDeclared,
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return string|null motif d'exclusion, ou null si le professionnel est éligible */
    public function exclusionReason(ProfessionalProfile $professional, ServiceRequest $request, \DateTimeImmutable $now): ?string
    {
        $user = $professional->getUser();
        $area = $request->getArea();

        return match (true) {
            !$user->isActive() => 'compte suspendu',
            !$professional->isVerified() => 'profil non vérifié',
            $user->getId()->equals($request->getClient()->getId()) => 'auteur de la demande',
            null === $professional->findOffering($request->getService()) => 'service non proposé',
            null === $area || !$professional->coversArea($area) => 'zone non couverte',
            !($professional->getAvailability()?->isAvailableAt($now) ?? false) => 'indisponible',
            $request->getService()->requiresQualification()
                && !$professional->hasValidDocument(DocumentType::Qualification, $now) => 'qualification manquante ou expirée',
            $this->isBusy($professional, $request, $now) => 'déjà engagé sur une autre mission',
            default => null,
        };
    }

    public function isEligible(ProfessionalProfile $professional, ServiceRequest $request, \DateTimeImmutable $now): bool
    {
        return null === $this->exclusionReason($professional, $request, $now);
    }

    /** Capacité à recevoir une nouvelle mission (§8.1) : ni intervention en cours, ni réservation ailleurs. */
    private function isBusy(ProfessionalProfile $professional, ServiceRequest $request, \DateTimeImmutable $now): bool
    {
        $interventions = (int) $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Intervention::class, 'i')
            ->where('i.professional = :professional')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('professional', $professional->getId(), 'uuid')
            ->setParameter('statuses', array_map(static fn (InterventionStatus $s) => $s->value, self::BUSY_INTERVENTION_STATUSES))
            ->getQuery()
            ->getSingleScalarResult();
        if ($interventions > 0) {
            return true;
        }

        $reservations = (int) $this->em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(RequestOffer::class, 'o')
            ->where('o.professional = :professional')
            ->andWhere('o.request != :request')
            ->andWhere('o.status = :reserved')
            ->andWhere('o.reservedUntil > :now')
            ->setParameter('professional', $professional->getId(), 'uuid')
            ->setParameter('request', $request->getId(), 'uuid')
            ->setParameter('reserved', OfferStatus::Reserved->value)
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->getQuery()
            ->getSingleScalarResult();

        return $reservations > 0;
    }
}
