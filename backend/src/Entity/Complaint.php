<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\ComplaintStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Réclamation ou litige, avec responsable et résolution tracés (§7.4). */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_complaint_status', columns: ['status', 'created_at'])]
class Complaint
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ServiceRequest $request;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $openedBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    #[ORM\Column(length: 40)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 16, enumType: ComplaintStatus::class)]
    private ComplaintStatus $status = ComplaintStatus::Open;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(ServiceRequest $request, User $openedBy, string $subject, string $description)
    {
        $this->id = Uuid::v7();
        $this->request = $request;
        $this->openedBy = $openedBy;
        $this->subject = $subject;
        $this->description = $description;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRequest(): ServiceRequest
    {
        return $this->request;
    }

    public function getOpenedBy(): User
    {
        return $this->openedBy;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function assignTo(User $agent): void
    {
        $this->assignedTo = $agent;
        if (ComplaintStatus::Open === $this->status) {
            $this->status = ComplaintStatus::InProgress;
        }
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStatus(): ComplaintStatus
    {
        return $this->status;
    }

    public function close(ComplaintStatus $status, string $resolution, \DateTimeImmutable $at): void
    {
        if (!\in_array($status, [ComplaintStatus::Resolved, ComplaintStatus::Rejected], true)) {
            throw new \InvalidArgumentException('Une réclamation se clôt en « resolved » ou « rejected ».');
        }
        $this->status = $status;
        $this->resolution = $resolution;
        $this->resolvedAt = $at;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
