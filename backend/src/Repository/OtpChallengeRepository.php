<?php

namespace App\Repository;

use App\Entity\OtpChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OtpChallenge> */
class OtpChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OtpChallenge::class);
    }

    /** Seul le dernier code émis est valable : un nouvel envoi invalide implicitement les précédents. */
    public function findLatestForPhone(string $phoneE164): ?OtpChallenge
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.phone = :phone')
            ->setParameter('phone', $phoneE164)
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
