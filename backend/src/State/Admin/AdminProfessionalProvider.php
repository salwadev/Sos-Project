<?php

namespace App\State\Admin;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Professional\ProfessionalProfileView;
use App\Entity\ProfessionalProfile;
use App\Enum\VerificationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Profils professionnels vus par l'administration (§7.2, §14.5) :
 * file de vérification filtrée par statut (défaut « pending »), plus anciens d'abord, 30 par page.
 *
 * @implements ProviderInterface<ProfessionalProfileView>
 */
final class AdminProfessionalProvider implements ProviderInterface
{
    private const PAGE_SIZE = 30;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return ProfessionalProfileView|list<ProfessionalProfileView>|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfessionalProfileView|array|null
    {
        if (!$operation instanceof CollectionOperationInterface) {
            $profile = self::find($this->em, $uriVariables['id'] ?? null);

            return null === $profile ? null : ProfessionalProfileView::forAdmin($profile);
        }

        $request = $context['request'] ?? null;
        $statusValue = $request instanceof Request ? $request->query->getString('verificationStatus', VerificationStatus::Pending->value) : VerificationStatus::Pending->value;
        $status = VerificationStatus::tryFrom($statusValue) ?? throw new BadRequestHttpException('Filtre verificationStatus inconnu.');
        $page = max(1, $request instanceof Request ? $request->query->getInt('page', 1) : 1);

        $profiles = $this->em->createQueryBuilder()
            ->select('p')
            ->from(ProfessionalProfile::class, 'p')
            ->where('p.verificationStatus = :status')
            ->setParameter('status', $status->value)
            ->orderBy('p.createdAt', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return array_map(ProfessionalProfileView::forAdmin(...), $profiles);
    }

    public static function find(EntityManagerInterface $em, mixed $id): ?ProfessionalProfile
    {
        return \is_string($id) && Uuid::isValid($id) ? $em->find(ProfessionalProfile::class, $id) : null;
    }
}
