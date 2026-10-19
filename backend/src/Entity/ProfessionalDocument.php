<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Justificatif déposé par un professionnel (§6.2, §7.2).
 * Le fichier vit dans un stockage objet privé ; on ne garde ici que sa clé (§11.4, §12.6).
 * Données sensibles : non visibles par le support par défaut (§4.4).
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_document_status_expiry', columns: ['status', 'expires_at'])]
class ProfessionalDocument
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProfessionalProfile $professional;

    #[ORM\Column(length: 32, enumType: DocumentType::class)]
    private DocumentType $type;

    #[ORM\Column(length: 255)]
    private string $storageKey;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $sizeBytes;

    #[ORM\Column(length: 16, enumType: DocumentStatus::class)]
    private DocumentStatus $status = DocumentStatus::Pending;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    public function __construct(ProfessionalProfile $professional, DocumentType $type, string $storageKey, string $mimeType, int $sizeBytes)
    {
        $this->id = Uuid::v7();
        $this->professional = $professional;
        $this->type = $type;
        $this->storageKey = $storageKey;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $professional->addDocument($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProfessional(): ProfessionalProfile
    {
        return $this->professional;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function review(DocumentStatus $status, User $reviewer, \DateTimeImmutable $at, ?string $rejectionReason = null): void
    {
        $this->status = $status;
        $this->reviewedBy = $reviewer;
        $this->reviewedAt = $at;
        $this->rejectionReason = DocumentStatus::Rejected === $status ? $rejectionReason : null;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function isValidOn(\DateTimeImmutable $date): bool
    {
        return DocumentStatus::Approved === $this->status
            && (null === $this->expiresAt || $this->expiresAt >= $date->setTime(0, 0));
    }
}
