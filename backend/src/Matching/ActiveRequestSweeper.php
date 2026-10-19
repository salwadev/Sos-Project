<?php

namespace App\Matching;

use App\Entity\ServiceRequest;
use App\Enum\ServiceRequestStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Fait avancer toutes les demandes en cours de matching (SUBMITTED, SEARCHING, OFFERED), de la plus ancienne
 * à la plus récente. Utilisé par le passage planifié (MatchingTick) et par la commande app:matching:dispatch.
 * Une erreur sur une demande est journalisée sans bloquer les suivantes.
 */
final class ActiveRequestSweeper
{
    private const ACTIVE_STATUSES = [
        ServiceRequestStatus::Submitted,
        ServiceRequestStatus::Searching,
        ServiceRequestStatus::Offered,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestDispatcher $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return array<string, int> nombre de demandes par résultat (DispatchOutcome, ou « error ») */
    public function sweep(): array
    {
        $ids = $this->em->createQueryBuilder()
            ->select('r.id')
            ->from(ServiceRequest::class, 'r')
            ->where('r.status IN (:statuses)')
            ->setParameter('statuses', array_map(static fn (ServiceRequestStatus $s) => $s->value, self::ACTIVE_STATUSES))
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $counts = [];
        foreach ($ids as $id) {
            $request = $this->em->find(ServiceRequest::class, $id);
            if (null === $request) {
                continue;
            }
            try {
                $outcome = $this->dispatcher->dispatch($request)->value;
            } catch (\Throwable $e) {
                $this->logger->error('Matching : échec sur la demande {reference}.', ['reference' => $request->getReference(), 'exception' => $e]);
                $outcome = 'error';
            }
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            $this->em->clear();
        }

        return $counts;
    }
}
