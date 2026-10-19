<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\DocumentType;
use App\Enum\VerificationStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Profil professionnel : métiers, zones, justificatifs et statut de vérification (§4.2, §6.1).
 * Le profil reste « pending » tant que les contrôles obligatoires ne sont pas terminés.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_professional_verification', columns: ['verification_status'])]
#[ORM\Index(name: 'idx_professional_base_location', columns: ['base_location'], flags: ['spatial'])]
class ProfessionalProfile
{
    use TimestampableTrait;

    public const LEGAL_INDIVIDUAL = 'individual';
    public const LEGAL_COMPANY = 'company';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: 'professionalProfile')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 120)]
    private string $displayName;

    #[ORM\Column(length: 20)]
    private string $legalStatus = self::LEGAL_INDIVIDUAL;

    #[ORM\Column(length: 16, enumType: VerificationStatus::class)]
    private VerificationStatus $verificationStatus = VerificationStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $verificationNote = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    /** Point de départ habituel (EWKT, SRID 4326), utilisé pour le calcul de distance (§8.2). */
    #[ORM\Column(type: PostGISType::GEOGRAPHY, nullable: true, options: ['geometry_type' => 'POINT', 'srid' => 4326])]
    private ?string $baseLocation = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $ratingAverage = null;

    #[ORM\Column]
    private int $ratingCount = 0;

    /** Taux de réponse aux sollicitations, entre 0 et 1 (§8.2). */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $responseRate = null;

    /** @var Collection<int, ServiceArea> */
    #[ORM\ManyToMany(targetEntity: ServiceArea::class)]
    #[ORM\JoinTable(name: 'professional_service_area')]
    private Collection $serviceAreas;

    /** @var Collection<int, ProfessionalService> */
    #[ORM\OneToMany(targetEntity: ProfessionalService::class, mappedBy: 'professional', cascade: ['persist'], orphanRemoval: true)]
    private Collection $services;

    /** @var Collection<int, ProfessionalDocument> */
    #[ORM\OneToMany(targetEntity: ProfessionalDocument::class, mappedBy: 'professional', cascade: ['persist'])]
    private Collection $documents;

    #[ORM\OneToOne(mappedBy: 'professional', cascade: ['persist'])]
    private ?Availability $availability = null;

    public function __construct(User $user, string $displayName)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->displayName = $displayName;
        $this->serviceAreas = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $user->setProfessionalProfile($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getLegalStatus(): string
    {
        return $this->legalStatus;
    }

    public function setLegalStatus(string $legalStatus): void
    {
        if (!\in_array($legalStatus, [self::LEGAL_INDIVIDUAL, self::LEGAL_COMPANY], true)) {
            throw new \InvalidArgumentException('Statut juridique inconnu.');
        }
        $this->legalStatus = $legalStatus;
    }

    public function getVerificationStatus(): VerificationStatus
    {
        return $this->verificationStatus;
    }

    public function setVerificationStatus(VerificationStatus $status, ?string $note, \DateTimeImmutable $at): void
    {
        $this->verificationStatus = $status;
        $this->verificationNote = $note;
        $this->verifiedAt = VerificationStatus::Verified === $status ? $at : $this->verifiedAt;
    }

    public function isVerified(): bool
    {
        return VerificationStatus::Verified === $this->verificationStatus;
    }

    public function getVerificationNote(): ?string
    {
        return $this->verificationNote;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function getBaseLocation(): ?string
    {
        return $this->baseLocation;
    }

    public function setBaseLocation(?string $baseLocation): void
    {
        $this->baseLocation = $baseLocation;
    }

    public function getRatingAverage(): ?float
    {
        return $this->ratingAverage;
    }

    public function getRatingCount(): int
    {
        return $this->ratingCount;
    }

    public function recordRating(int $rating): void
    {
        $total = ($this->ratingAverage ?? 0.0) * $this->ratingCount + $rating;
        ++$this->ratingCount;
        $this->ratingAverage = round($total / $this->ratingCount, 2);
    }

    public function getResponseRate(): ?float
    {
        return $this->responseRate;
    }

    public function setResponseRate(?float $responseRate): void
    {
        $this->responseRate = $responseRate;
    }

    /** @return Collection<int, ServiceArea> */
    public function getServiceAreas(): Collection
    {
        return $this->serviceAreas;
    }

    public function addServiceArea(ServiceArea $area): void
    {
        if (!$this->serviceAreas->contains($area)) {
            $this->serviceAreas->add($area);
        }
    }

    /**
     * Remplace les zones couvertes par celles données (seules les différences sont écrites).
     *
     * @param list<ServiceArea> $areas
     */
    public function replaceServiceAreas(array $areas): void
    {
        foreach ($this->serviceAreas->toArray() as $covered) {
            $kept = array_filter($areas, static fn (ServiceArea $a) => $a->getId()->equals($covered->getId()));
            if ([] === $kept) {
                $this->serviceAreas->removeElement($covered);
            }
        }
        foreach ($areas as $area) {
            if (!$this->coversArea($area)) {
                $this->serviceAreas->add($area);
            }
        }
    }

    public function coversArea(ServiceArea $area): bool
    {
        foreach ($this->serviceAreas as $covered) {
            if ($covered->getId()->equals($area->getId())) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, ProfessionalService> */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(ProfessionalService $service): void
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
        }
    }

    /** Le service n'est plus proposé : la ligne est supprimée (orphanRemoval). */
    public function removeService(ProfessionalService $service): void
    {
        $this->services->removeElement($service);
    }

    /** Tarifs du professionnel pour ce service, ou null s'il ne le propose pas. */
    public function findOffering(Service $service): ?ProfessionalService
    {
        foreach ($this->services as $offering) {
            if ($offering->getService()->getId()->equals($service->getId())) {
                return $offering;
            }
        }

        return null;
    }

    /** @return Collection<int, ProfessionalDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(ProfessionalDocument $document): void
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
        }
    }

    public function findDocument(string $id): ?ProfessionalDocument
    {
        foreach ($this->documents as $document) {
            if ($document->getId()->toRfc4122() === strtolower($id)) {
                return $document;
            }
        }

        return null;
    }

    public function hasValidDocument(DocumentType $type, \DateTimeImmutable $on): bool
    {
        foreach ($this->documents as $document) {
            if ($type === $document->getType() && $document->isValidOn($on)) {
                return true;
            }
        }

        return false;
    }

    public function getAvailability(): ?Availability
    {
        return $this->availability;
    }

    public function setAvailability(Availability $availability): void
    {
        $this->availability = $availability;
    }
}
