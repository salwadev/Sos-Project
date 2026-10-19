<?php

namespace App\Matching;

use App\Entity\ServiceRequest;
use App\Enum\UserStatus;
use App\Enum\VerificationStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Présélection et classement des professionnels pour une demande (§8.1, §8.2).
 * Filtre en SQL sur les critères indexés (service, zone, fenêtre de disponibilité, statut)
 * puis classe par distance PostGIS (géographie, en mètres), taux de réponse et note moyenne.
 * Les critères complets sont réappliqués par ProfessionalEligibility.
 */
final class CandidateFinder
{
    private const MAX_CANDIDATES = 200;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param list<string> $excludedProfessionalIds professionnels déjà sollicités pour cette demande
     *
     * @return list<array{id: string, distanceMeters: float|null}> du plus pertinent au moins pertinent
     */
    public function rankedCandidates(ServiceRequest $request, \DateTimeImmutable $now, array $excludedProfessionalIds = []): array
    {
        $area = $request->getArea();
        if (null === $area) {
            return [];
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT p.id,
                       ST_Distance(COALESCE(a.current_location, p.base_location), r.location) AS distance_meters
                FROM professional_profile p
                JOIN app_user u ON u.id = p.user_id
                JOIN availability a ON a.professional_id = p.id
                JOIN professional_service ps ON ps.professional_id = p.id AND ps.service_id = :service
                JOIN professional_service_area psa ON psa.professional_profile_id = p.id AND psa.service_area_id = :area
                JOIN service_request r ON r.id = :request
                WHERE u.status = :activeUser
                  AND u.id <> r.client_id
                  AND p.verification_status = :verified
                  AND a.available = true
                  AND a.available_until > :now
                  AND p.id NOT IN (:excluded)
                ORDER BY distance_meters ASC NULLS LAST,
                         p.response_rate DESC NULLS LAST,
                         p.rating_average DESC NULLS LAST,
                         p.id ASC
                LIMIT :limit
                SQL,
            [
                'service' => $request->getService()->getId()->toRfc4122(),
                'area' => $area->getId()->toRfc4122(),
                'request' => $request->getId()->toRfc4122(),
                'activeUser' => UserStatus::Active->value,
                'verified' => VerificationStatus::Verified->value,
                'now' => $now->format('Y-m-d H:i:sP'),
                // NOT IN () est invalide : un UUID nul ne correspond à aucun profil.
                'excluded' => [] === $excludedProfessionalIds ? ['00000000-0000-0000-0000-000000000000'] : $excludedProfessionalIds,
                'limit' => self::MAX_CANDIDATES,
            ],
            ['excluded' => ArrayParameterType::STRING],
        );

        return array_map(static fn (array $row) => [
            'id' => (string) $row['id'],
            'distanceMeters' => null === $row['distance_meters'] ? null : (float) $row['distance_meters'],
        ], $rows);
    }

    /** Distance approximative (mètres) entre la demande et la position connue du professionnel. */
    public function distanceMeters(string $requestId, string $professionalId): ?float
    {
        $distance = $this->em->getConnection()->fetchOne(
            <<<'SQL'
                SELECT ST_Distance(COALESCE(a.current_location, p.base_location), r.location)
                FROM professional_profile p
                LEFT JOIN availability a ON a.professional_id = p.id
                JOIN service_request r ON r.id = :request
                WHERE p.id = :professional
                SQL,
            ['request' => $requestId, 'professional' => $professionalId],
        );

        return null === $distance || false === $distance ? null : (float) $distance;
    }
}
