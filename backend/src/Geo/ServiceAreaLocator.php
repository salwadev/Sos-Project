<?php

namespace App\Geo;

use App\Entity\ServiceArea;
use Doctrine\ORM\EntityManagerInterface;

/** Trouve la zone active qui couvre un point, via un index spatial PostGIS (§12.3). */
final class ServiceAreaLocator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findActiveAreaCovering(string $pointEwkt): ?ServiceArea
    {
        $id = $this->em->getConnection()->fetchOne(
            'SELECT id FROM service_area WHERE active = true AND ST_Covers(boundary, ST_GeogFromText(:point)) LIMIT 1',
            ['point' => $pointEwkt],
        );

        return false === $id ? null : $this->em->find(ServiceArea::class, $id);
    }
}
