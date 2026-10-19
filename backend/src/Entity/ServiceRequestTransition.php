<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Historique horodaté des changements d'état d'une demande (§5.7, §8.4, §13.3). Écrit une fois, jamais modifié. */
#[ORM\Entity]
#[ORM\Index(name: 'idx_request_transition_request', columns: ['request_id', 'occurred_at'])]
class ServiceRequestTransition
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'transitions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ServiceRequest $request;

    #[ORM\Column(length: 40)]
    private string $transition;

    #[ORM\Column(length: 20)]
    private string $fromStatus;

    #[ORM\Column(length: 20)]
    private string $toStatus;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $actor;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        ServiceRequest $request,
        string $transition,
        string $fromStatus,
        string $toStatus,
        ?User $actor,
        ?string $reason,
        \DateTimeImmutable $occurredAt,
    ) {
        $this->id = Uuid::v7();
        $this->request = $request;
        $this->transition = $transition;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->actor = $actor;
        $this->reason = $reason;
        $this->occurredAt = $occurredAt;
        $request->addTransition($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRequest(): ServiceRequest
    {
        return $this->request;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }

    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
