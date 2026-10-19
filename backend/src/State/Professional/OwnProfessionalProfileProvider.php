<?php

namespace App\State\Professional;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Professional\ProfessionalProfileView;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<ProfessionalProfileView> */
final class OwnProfessionalProfileProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfessionalProfileView
    {
        $user = $this->security->getUser();
        $professional = $user instanceof User ? $user->getProfessionalProfile() : null;
        if (null === $professional) {
            throw new NotFoundHttpException('Aucun profil professionnel.');
        }

        return ProfessionalProfileView::of($professional);
    }
}
