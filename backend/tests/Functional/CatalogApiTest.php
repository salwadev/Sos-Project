<?php

namespace App\Tests\Functional;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Factory;

/** §3.2, §14.2 : catalogue public, limité aux éléments activés. */
final class CatalogApiTest extends ApiTestCase
{
    public function testCatalogueIsPublicAndHidesInactiveItems(): void
    {
        $em = $this->em();
        $home = Factory::category($em, 'home');
        $car = Factory::category($em, 'car');
        $car->setActive(false);
        Factory::service($em, $home, 'plumbing');
        $locksmith = Factory::service($em, $home, 'locksmith');
        $locksmith->setActive(false);
        Factory::laMarsa($em);
        Factory::area($em, 'ariana', [10.15, 36.85, 10.22, 36.90], active: false);
        $em->flush();

        $categories = $this->api('GET', '/api/categories');
        self::assertSame(200, $this->httpStatus());
        self::assertSame(['home'], array_column($categories, 'code'));

        $services = $this->api('GET', '/api/services');
        self::assertSame(['plumbing'], array_column($services, 'code'));

        $areas = $this->api('GET', '/api/service-areas');
        self::assertCount(1, $areas);
        self::assertArrayNotHasKey('boundary', $areas[0], 'Les contours ne sont pas exposés.');
    }
}
