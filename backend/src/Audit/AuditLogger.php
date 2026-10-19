<?php

namespace App\Audit;

use App\Domain\Clock;
use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Journal d'audit chaîné (§7.6). Les entrées sont écrites sous verrou consultatif PostgreSQL
 * pour que deux écritures concurrentes ne partagent jamais le même hash précédent.
 * Ne jamais mettre de secret ni de donnée de santé dans le contexte (§12.7).
 */
final class AuditLogger
{
    private const LOCK_KEY = 74_201_026;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogRepository $repository,
    ) {
    }

    /** @param array<string, scalar|null> $context */
    public function record(string $action, ?User $actor, string $subjectType, ?string $subjectId, array $context = []): AuditLog
    {
        return $this->em->wrapInTransaction(function () use ($action, $actor, $subjectType, $subjectId, $context): AuditLog {
            $this->em->getConnection()->executeStatement('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
            $entry = new AuditLog(
                $action,
                $actor?->getUserIdentifier(),
                $subjectType,
                $subjectId,
                $context,
                Clock::now(),
                $this->repository->findLastHash(),
            );
            $this->em->persist($entry);
            $this->em->flush();

            return $entry;
        });
    }

    /**
     * Vérifie l'intégrité de toute la chaîne.
     *
     * @return list<string> identifiants des entrées altérées ou mal chaînées (vide si intègre)
     */
    public function verifyChain(): array
    {
        $broken = [];
        $previous = null;
        foreach ($this->repository->findBy([], ['id' => 'ASC']) as $entry) {
            if ($entry->getPreviousHash() !== $previous || $entry->computeHash() !== $entry->getHash()) {
                $broken[] = $entry->getId()->toRfc4122();
            }
            $previous = $entry->getHash();
        }

        return $broken;
    }
}
