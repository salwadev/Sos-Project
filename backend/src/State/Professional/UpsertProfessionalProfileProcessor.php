<?php

namespace App\State\Professional;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Professional\ProfessionalProfileInput;
use App\ApiResource\Professional\ProfessionalProfileView;
use App\Audit\AuditLogger;
use App\Domain\Clock;
use App\Domain\GeoPoint;
use App\Entity\ProfessionalProfile;
use App\Entity\ProfessionalService;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\VerificationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * POST /api/professional/profile : inscription ou mise à jour du profil professionnel (§6.1).
 * - À la création, le compte reçoit ROLE_PROFESSIONAL et le profil reste « pending » (§6.1, §6.2).
 * - Un profil rejeté qui est modifié repart en vérification.
 * - Un profil vérifié garde son statut, sauf si le nom affiché ou le statut juridique change : ce sont
 *   les éléments contrôlés à la vérification, il repasse donc « pending ».
 * - Un profil suspendu ne peut pas être modifié : seule l'administration le réactive (§7.2).
 *
 * @implements ProcessorInterface<ProfessionalProfileInput, ProfessionalProfileView>
 */
final class UpsertProfessionalProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly AuditLogger $audit,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfessionalProfileView
    {
        \assert($data instanceof ProfessionalProfileInput);
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $profile = $user->getProfessionalProfile();
        if (VerificationStatus::Suspended === $profile?->getVerificationStatus()) {
            throw new ConflictHttpException('Profil suspendu : il ne peut pas être modifié. Contactez le support.');
        }

        $offerings = $this->resolveServices($data);
        $areas = $this->resolveAreas($data->serviceAreaCodes);
        $displayName = trim((string) $data->displayName);
        $legalStatus = (string) $data->legalStatus;
        $now = Clock::now();

        $created = null === $profile;
        if ($created) {
            $profile = new ProfessionalProfile($user, $displayName);
            $this->em->persist($profile);
        }
        $previousStatus = $profile->getVerificationStatus();
        $identityChanged = !$created && ($displayName !== $profile->getDisplayName() || $legalStatus !== $profile->getLegalStatus());

        $user->addRole(UserRole::Professional);
        $profile->setDisplayName($displayName);
        $profile->setLegalStatus($legalStatus);
        $profile->setBaseLocation(GeoPoint::toEwkt((float) $data->baseLatitude, (float) $data->baseLongitude));
        $profile->replaceServiceAreas($areas);
        $this->syncServices($profile, $offerings);

        if (VerificationStatus::Rejected === $previousStatus
            || (VerificationStatus::Verified === $previousStatus && $identityChanged)) {
            $profile->setVerificationStatus(VerificationStatus::Pending, null, $now);
        }

        $this->em->flush();

        $this->audit->record(
            $created ? 'professional.profile_created' : 'professional.profile_updated',
            $user,
            'ProfessionalProfile',
            $profile->getId()->toRfc4122(),
            [
                'from' => $created ? null : $previousStatus->value,
                'to' => $profile->getVerificationStatus()->value,
                'services' => implode(',', array_keys($offerings)),
                'areas' => implode(',', array_map(static fn (ServiceArea $a) => $a->getCode(), $areas)),
            ],
        );

        return ProfessionalProfileView::of($profile);
    }

    /** @return array<string, array{Service, int, int|null}> code => [service, frais de déplacement, frais de diagnostic] */
    private function resolveServices(ProfessionalProfileInput $data): array
    {
        $codes = array_map(static fn ($s) => (string) $s->serviceCode, $data->services);
        $found = [];
        foreach ($this->em->getRepository(Service::class)->findBy(['code' => $codes, 'active' => true]) as $service) {
            if ($service->getCategory()->isActive()) {
                $found[$service->getCode()] = $service;
            }
        }

        $offerings = [];
        foreach ($data->services as $input) {
            $service = $found[$input->serviceCode] ?? throw new UnprocessableEntityHttpException(\sprintf('Service inconnu ou indisponible : %s.', $input->serviceCode));
            $offerings[$service->getCode()] = [$service, (int) $input->travelFeeMillimes, $input->diagnosticFeeMillimes];
        }
        ksort($offerings);

        return $offerings;
    }

    /**
     * @param list<string> $codes
     *
     * @return list<ServiceArea>
     */
    private function resolveAreas(array $codes): array
    {
        $areas = $this->em->getRepository(ServiceArea::class)->findBy(['code' => $codes], ['code' => 'ASC']);
        $unknown = array_diff($codes, array_map(static fn (ServiceArea $a) => $a->getCode(), $areas));
        if ([] !== $unknown) {
            throw new UnprocessableEntityHttpException(\sprintf('Zone inconnue : %s.', implode(', ', $unknown)));
        }

        return $areas;
    }

    /**
     * Met à jour en place les services déjà proposés (contrainte d'unicité professionnel/service),
     * ajoute les nouveaux et retire ceux qui ne figurent plus dans la requête.
     *
     * @param array<string, array{Service, int, int|null}> $offerings
     */
    private function syncServices(ProfessionalProfile $profile, array $offerings): void
    {
        foreach ($profile->getServices()->toArray() as $existing) {
            if (!isset($offerings[$existing->getService()->getCode()])) {
                $profile->removeService($existing);
            }
        }
        foreach ($offerings as [$service, $travelFee, $diagnosticFee]) {
            $offering = $profile->findOffering($service) ?? new ProfessionalService($profile, $service, $travelFee);
            $offering->setTravelFeeMillimes($travelFee);
            $offering->setDiagnosticFeeMillimes($diagnosticFee);
        }
    }
}
