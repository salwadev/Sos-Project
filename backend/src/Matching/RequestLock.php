<?php

namespace App\Matching;

use App\Entity\ServiceRequest;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sérialise les décisions sur une demande (§6.5, §16.3) : transaction + SELECT … FOR UPDATE sur la ligne
 * service_request, puis rechargement de son état. Deux acceptations simultanées s'exécutent donc l'une après
 * l'autre, et la seconde voit l'état laissé par la première.
 *
 * Le travail doit valider avant de modifier : en cas d'exception la transaction est annulée sans fermer
 * l'EntityManager (contrairement à wrapInTransaction), pour que l'appelant puisse répondre proprement.
 */
final class RequestLock
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @template T
     *
     * @param callable(ServiceRequest): T $work
     *
     * @return T
     */
    public function run(ServiceRequest $request, callable $work): mixed
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->em->refresh($request, LockMode::PESSIMISTIC_WRITE);
            $result = $work($request);
            $this->em->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
