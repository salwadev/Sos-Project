<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Journal d'audit des actions sensibles (§4.3, §7.6).
 * Chaque entrée est chaînée à la précédente par un hash : toute modification ou suppression
 * a posteriori casse la chaîne et est détectée par AuditLogger::verifyChain().
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(name: 'idx_audit_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_audit_created', columns: ['created_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $actorId;

    #[ORM\Column(length: 60)]
    private string $subjectType;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $subjectId;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $context;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $previousHash;

    #[ORM\Column(length: 64, unique: true)]
    private string $hash;

    /** @param array<string, mixed> $context */
    public function __construct(
        string $action,
        ?string $actorId,
        string $subjectType,
        ?string $subjectId,
        array $context,
        \DateTimeImmutable $createdAt,
        ?string $previousHash,
    ) {
        $this->id = Uuid::v7();
        $this->action = $action;
        $this->actorId = $actorId;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->context = $context;
        // Seconde entière en UTC : c'est la précision conservée par la colonne timestamptz.
        $utc = $createdAt->setTimezone(new \DateTimeZone('UTC'));
        $this->createdAt = $utc->setTime((int) $utc->format('H'), (int) $utc->format('i'), (int) $utc->format('s'));
        $this->previousHash = $previousHash;
        $this->hash = $this->computeHash();
    }

    /** Hash SHA-256 du contenu canonique de l'entrée et du hash précédent. */
    public function computeHash(): string
    {
        return hash('sha256', json_encode([
            $this->id->toRfc4122(),
            $this->action,
            $this->actorId,
            $this->subjectType,
            $this->subjectId,
            $this->context,
            $this->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            $this->previousHash,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPreviousHash(): ?string
    {
        return $this->previousHash;
    }

    public function getHash(): string
    {
        return $this->hash;
    }
}
