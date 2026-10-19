<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Mouvement financier traçable (§9.6). Au lancement : paiement direct au professionnel, déclaré (§9.3).
 * Aucune donnée de carte n'est stockée.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Intervention $intervention;

    #[ORM\Column(length: 16, enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column(length: 16, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Declared;

    #[ORM\Column]
    private int $grossMillimes;

    #[ORM\Column(length: 3)]
    private string $currency = 'TND';

    /** Référence du prestataire de paiement, si applicable. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $providerReference = null;

    public function __construct(Intervention $intervention, PaymentMethod $method, int $grossMillimes)
    {
        if ($grossMillimes <= 0) {
            throw new \InvalidArgumentException('Montant de paiement invalide.');
        }
        $this->id = Uuid::v7();
        $this->intervention = $intervention;
        $this->method = $method;
        $this->grossMillimes = $grossMillimes;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIntervention(): Intervention
    {
        return $this->intervention;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): void
    {
        $this->status = $status;
    }

    public function getGrossMillimes(): int
    {
        return $this->grossMillimes;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function setProviderReference(?string $reference): void
    {
        $this->providerReference = $reference;
    }
}
