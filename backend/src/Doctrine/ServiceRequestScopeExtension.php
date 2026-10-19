<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /api/requests ne renvoie que ce que l'utilisateur a le droit de voir (§16.6) :
 * ses propres demandes, toutes pour l'admin, les non sensibles pour le support.
 */
final class ServiceRequestScopeExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (ServiceRequest::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();
        $alias = $queryBuilder->getRootAliases()[0];

        if (!$user instanceof User) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }
        if ($user->hasRole(UserRole::Admin)) {
            return;
        }
        if ($user->hasRole(UserRole::Support)) {
            $service = $queryNameGenerator->generateJoinAlias('service');
            $category = $queryNameGenerator->generateJoinAlias('category');
            $queryBuilder
                ->join(\sprintf('%s.service', $alias), $service)
                ->join(\sprintf('%s.category', $service), $category)
                ->andWhere(\sprintf('%s.sensitive = false', $category));

            return;
        }

        $param = $queryNameGenerator->generateParameterName('client');
        $queryBuilder->andWhere(\sprintf('%s.client = :%s', $alias, $param))->setParameter($param, $user->getId(), 'uuid');
    }
}
