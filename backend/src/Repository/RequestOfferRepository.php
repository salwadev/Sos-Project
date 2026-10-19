<?php

namespace App\Repository;

use App\Entity\ProfessionalProfile;
use App\Entity\RequestOffer;
use App\Entity\ServiceRequest;
use App\Enum\OfferStatus;
use App\Enum\ServiceRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RequestOffer> */
class RequestOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequestOffer::class);
    }

    /**
     * Toutes les sollicitations d'une demande, relues en base (à appeler sous RequestLock).
     *
     * @return list<RequestOffer>
     */
    public function findForRequest(ServiceRequest $request): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.request = :request')
            ->setParameter('request', $request->getId(), 'uuid')
            ->orderBy('o.wave', 'ASC')
            ->addOrderBy('o.sentAt', 'ASC')
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

    /**
     * Sollicitations auxquelles le professionnel peut encore répondre, et ses réservations en cours (§6.4).
     *
     * @return list<RequestOffer>
     */
    public function findOpenForProfessional(ProfessionalProfile $professional, \DateTimeImmutable $now): array
    {
        $qb = $this->createQueryBuilder('o');

        return $qb
            ->addSelect('r', 's')
            ->join('o.request', 'r')
            ->join('r.service', 's')
            ->where('o.professional = :professional')
            ->andWhere($qb->expr()->orX(
                'o.status = :sent AND o.respondBefore > :now AND r.status = :searching',
                'o.status = :reserved AND r.status = :offered',
            ))
            ->setParameter('professional', $professional->getId(), 'uuid')
            ->setParameter('sent', OfferStatus::Sent->value)
            ->setParameter('reserved', OfferStatus::Reserved->value)
            ->setParameter('searching', ServiceRequestStatus::Searching->value)
            ->setParameter('offered', ServiceRequestStatus::Offered->value)
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->orderBy('o.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Offres présentées au client : seulement celles qu'un professionnel a acceptées (§5.5).
     *
     * @return list<RequestOffer>
     */
    public function findVisibleToClient(ServiceRequest $request): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('p')
            ->join('o.professional', 'p')
            ->where('o.request = :request')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('request', $request->getId(), 'uuid')
            ->setParameter('statuses', [OfferStatus::Reserved->value, OfferStatus::Confirmed->value, OfferStatus::Rejected->value])
            ->orderBy('o.respondedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
