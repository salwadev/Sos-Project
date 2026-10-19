<?php

namespace App\ApiResource\Professional;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiResource\Admin\VerificationDecisionInput;
use App\Domain\GeoPoint;
use App\Entity\ProfessionalProfile;
use App\State\Admin\AdminProfessionalProvider;
use App\State\Admin\ReviewProfessionalProcessor;
use App\State\Professional\OwnProfessionalProfileProvider;
use App\State\Professional\UpsertProfessionalProfileProcessor;

/**
 * Profil professionnel tel qu'exposé par l'API, au professionnel lui-même (§6.1, §14.4)
 * et à l'administration qui le vérifie (§7.2, §14.5). Le numéro de téléphone n'est inclus que pour l'admin.
 */
#[ApiResource(
    shortName: 'ProfessionalProfile',
    operations: [
        new Get(
            uriTemplate: '/professional/profile',
            security: "is_granted('ROLE_PROFESSIONAL')",
            provider: OwnProfessionalProfileProvider::class,
        ),
        new Post(
            uriTemplate: '/professional/profile',
            status: 200,
            security: "is_granted('ROLE_USER')",
            input: ProfessionalProfileInput::class,
            processor: UpsertProfessionalProfileProcessor::class,
            description: 'Crée ou met à jour le profil du compte connecté, qui reçoit le rôle professionnel. Le profil reste « pending » jusqu\'à la vérification.',
        ),
    ],
)]
#[ApiResource(
    shortName: 'AdminProfessional',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/professionals',
            paginationEnabled: false,
            description: 'Profils à traiter, plus anciens d\'abord. Filtre « verificationStatus » (défaut : pending), « page » (30 par page).',
        ),
        new Get(uriTemplate: '/admin/professionals/{id}'),
        new Patch(
            uriTemplate: '/admin/professionals/{id}/verification',
            input: VerificationDecisionInput::class,
            processor: ReviewProfessionalProcessor::class,
            description: 'Approuve, rejette, suspend ou réactive le profil et/ou statue sur ses justificatifs. Motif obligatoire, journalisé.',
        ),
    ],
    security: "is_granted('ROLE_ADMIN')",
    provider: AdminProfessionalProvider::class,
)]
final class ProfessionalProfileView
{
    /**
     * @param list<array{serviceCode: string, serviceName: string, travelFeeMillimes: int, diagnosticFeeMillimes: int|null}> $services
     * @param list<array{code: string, name: string, active: bool}>                                                          $serviceAreas
     * @param list<array<string, mixed>>                                                                                      $documents    voir ProfessionalDocumentView::describe()
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly string $id,
        public readonly string $displayName,
        public readonly string $legalStatus,
        public readonly string $verificationStatus,
        public readonly ?string $verificationNote,
        public readonly ?\DateTimeImmutable $verifiedAt,
        public readonly ?float $baseLatitude,
        public readonly ?float $baseLongitude,
        public readonly array $services,
        public readonly array $serviceAreas,
        public readonly array $documents,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?string $phone = null,
    ) {
    }

    public static function of(ProfessionalProfile $profile): self
    {
        return self::build($profile, null);
    }

    public static function forAdmin(ProfessionalProfile $profile): self
    {
        return self::build($profile, $profile->getUser()->getPhone());
    }

    private static function build(ProfessionalProfile $profile, ?string $phone): self
    {
        [$latitude, $longitude] = GeoPoint::fromEwkt($profile->getBaseLocation()) ?? [null, null];

        $services = [];
        foreach ($profile->getServices() as $offering) {
            $services[] = [
                'serviceCode' => $offering->getService()->getCode(),
                'serviceName' => $offering->getService()->getNameFr(),
                'travelFeeMillimes' => $offering->getTravelFeeMillimes(),
                'diagnosticFeeMillimes' => $offering->getDiagnosticFeeMillimes(),
            ];
        }
        usort($services, static fn (array $a, array $b) => $a['serviceCode'] <=> $b['serviceCode']);

        $areas = [];
        foreach ($profile->getServiceAreas() as $area) {
            $areas[] = ['code' => $area->getCode(), 'name' => $area->getName(), 'active' => $area->isActive()];
        }
        usort($areas, static fn (array $a, array $b) => $a['code'] <=> $b['code']);

        $documents = [];
        foreach ($profile->getDocuments() as $document) {
            $documents[] = ProfessionalDocumentView::describe($document);
        }
        usort($documents, static fn (array $a, array $b) => $a['uploadedAt'] <=> $b['uploadedAt'] ?: $a['id'] <=> $b['id']);

        return new self(
            $profile->getId()->toRfc4122(),
            $profile->getDisplayName(),
            $profile->getLegalStatus(),
            $profile->getVerificationStatus()->value,
            $profile->getVerificationNote(),
            $profile->getVerifiedAt(),
            $latitude,
            $longitude,
            $services,
            $areas,
            $documents,
            $profile->getCreatedAt(),
            $phone,
        );
    }
}
