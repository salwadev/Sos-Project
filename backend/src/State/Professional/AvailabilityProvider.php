<?php

namespace App\State\Professional;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Professional\AvailabilityStatus;
use App\Domain\Clock;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProviderInterface<AvailabilityStatus> */
final class AvailabilityProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AvailabilityStatus
    {
        $user = $this->security->getUser();
        $professional = $user instanceof User ? $user->getProfessionalProfile() : null;
        if (null === $professional) {
            throw new AccessDeniedHttpException('Profil professionnel requis.');
        }

        return AvailabilityStatus::of($professional->getAvailability(), Clock::now());
    }
}
