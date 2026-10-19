<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\NotificationChannel;
use App\Enum\NotificationStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Notification envoyée à un utilisateur, avec relances (§12.5).
 * Le payload ne contient jamais de donnée sensible (santé, adresse exacte, code OTP — §16.7).
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_notification_status', columns: ['status', 'created_at'])]
class Notification
{
    use TimestampableTrait;

    public const MAX_ATTEMPTS = 5;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(length: 60)]
    private string $type;

    #[ORM\Column(length: 8, enumType: NotificationChannel::class)]
    private NotificationChannel $channel;

    #[ORM\Column(length: 16, enumType: NotificationStatus::class)]
    private NotificationStatus $status = NotificationStatus::Pending;

    /** @var array<string, scalar|null> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    /** @param array<string, scalar|null> $payload */
    public function __construct(User $recipient, string $type, NotificationChannel $channel, array $payload = [])
    {
        $this->id = Uuid::v7();
        $this->recipient = $recipient;
        $this->type = $type;
        $this->channel = $channel;
        $this->payload = $payload;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getChannel(): NotificationChannel
    {
        return $this->channel;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    /** @return array<string, scalar|null> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function markSent(\DateTimeImmutable $at): void
    {
        ++$this->attempts;
        $this->status = NotificationStatus::Sent;
        $this->sentAt = $at;
    }

    public function markFailedAttempt(): void
    {
        ++$this->attempts;
        $this->status = $this->attempts >= self::MAX_ATTEMPTS ? NotificationStatus::Failed : NotificationStatus::Pending;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function markRead(\DateTimeImmutable $at): void
    {
        $this->readAt ??= $at;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }
}
