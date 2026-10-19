<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\ServiceCategory;
use Doctrine\ORM\QueryBuilder;

/** Le catalogue public n'expose que les catégories, services et zones activés (§3.2). */
final class ActiveCatalogExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    private const CLASSES = [ServiceCategory::class, Service::class, ServiceArea::class];

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->onlyActive($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->onlyActive($queryBuilder, $resourceClass);
    }

    private function onlyActive(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (\in_array($resourceClass, self::CLASSES, true)) {
            $queryBuilder->andWhere(\sprintf('%s.active = true', $queryBuilder->getRootAliases()[0]));
        }
    }
}
