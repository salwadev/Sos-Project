<?php

namespace App\State\Professional;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Professional\AvailabilityStatus;
use App\ApiResource\Professional\UpdateAvailabilityInput;
use App\Domain\Clock;
use App\Domain\GeoPoint;
use App\Entity\Availability;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Passe le professionnel connecté disponible pour une durée bornée, ou indisponible (§6.3).
 *
 * @implements ProcessorInterface<UpdateAvailabilityInput, AvailabilityStatus>
 */
final class UpdateAvailabilityProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        #[Autowire('%app.availability.default_minutes%')]
        private readonly int $defaultMinutes,
        #[Autowire('%app.availability.max_minutes%')]
        private readonly int $maxMinutes,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AvailabilityStatus
    {
        \assert($data instanceof UpdateAvailabilityInput);
        $user = $this->security->getUser();
        $professional = $user instanceof User ? $user->getProfessionalProfile() : null;
        if (null === $professional) {
            throw new AccessDeniedHttpException('Profil professionnel requis.');
        }

        $minutes = $data->durationMinutes ?? $this->defaultMinutes;
        if ($minutes > $this->maxMinutes) {
            throw new UnprocessableEntityHttpException(\sprintf('Durée maximale : %d minutes, à reconfirmer ensuite.', $this->maxMinutes));
        }

        $availability = $professional->getAvailability();
        if (null === $availability) {
            $availability = new Availability($professional);
            $this->em->persist($availability);
        }

        $now = Clock::now();
        if ($data->available) {
            $availability->goAvailable($now, $now->modify(\sprintf('+%d minutes', $minutes)));
        } else {
            $availability->goUnavailable();
        }
        if (null !== $data->latitude && null !== $data->longitude) {
            $availability->setCurrentLocation(GeoPoint::toEwkt($data->latitude, $data->longitude));
        }
        $this->em->flush();

        return AvailabilityStatus::of($availability, $now);
    }
}
