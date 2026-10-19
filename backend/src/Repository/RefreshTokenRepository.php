<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RefreshToken> */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findOneByHash(string $tokenHash): ?RefreshToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /** Révoque tous les jetons actifs d'un utilisateur (réutilisation suspecte d'un jeton). */
    public function revokeAllFor(User $user, \DateTimeImmutable $at): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.revokedAt', ':at')
            ->andWhere('t.user = :user')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('at', $at, 'datetimetz_immutable')
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
