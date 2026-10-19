<?php

namespace App\Entity;

use App\Repository\OtpChallengeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Code de connexion envoyé par SMS (§5.1).
 * Seul un hash HMAC du code est stocké ; il expire, est à usage unique et le nombre d'essais est limité.
 */
#[ORM\Entity(repositoryClass: OtpChallengeRepository::class)]
#[ORM\Index(name: 'idx_otp_phone_created', columns: ['phone', 'created_at'])]
class OtpChallenge
{
    public const MAX_ATTEMPTS = 5;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 16)]
    private string $phone;

    #[ORM\Column(length: 64)]
    private string $codeHash;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    public function __construct(string $phone, string $codeHash, \DateTimeImmutable $createdAt, \DateTimeImmutable $expiresAt)
    {
        $this->id = Uuid::v7();
        $this->phone = $phone;
        $this->codeHash = $codeHash;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->consumedAt && $now < $this->expiresAt && $this->attempts < self::MAX_ATTEMPTS;
    }

    public function registerFailedAttempt(): void
    {
        ++$this->attempts;
    }

    public function consume(\DateTimeImmutable $at): void
    {
        $this->consumedAt = $at;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }
}
