<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditLog> */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /** Hash de la dernière entrée (UUID v7 : l'ordre des identifiants suit l'ordre d'écriture). */
    public function findLastHash(): ?string
    {
        $hash = $this->createQueryBuilder('a')
            ->select('a.hash')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $hash['hash'] ?? null;
    }
}
