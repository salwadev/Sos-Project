<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\CommissionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Commission sur une intervention terminée (§9.4).
 * La contrainte d'unicité sur l'intervention garantit qu'elle n'est calculée qu'une seule fois (§16.1).
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_commission_professional_status', columns: ['professional_id', 'status'])]
class Commission
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Intervention $intervention;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ProfessionalProfile $professional;

    /** Assiette de calcul en millimes (montant du service selon les conditions). */
    #[ORM\Column]
    private int $baseMillimes;

    /** Taux en points de base : 1000 = 10 %. */
    #[ORM\Column]
    private int $rateBasisPoints;

    #[ORM\Column]
    private int $amountMillimes;

    #[ORM\Column(length: 3)]
    private string $currency = 'TND';

    #[ORM\Column(length: 16, enumType: CommissionStatus::class)]
    private CommissionStatus $status = CommissionStatus::Due;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct(Intervention $intervention, int $baseMillimes, int $rateBasisPoints)
    {
        if ($baseMillimes < 0 || $rateBasisPoints < 0 || $rateBasisPoints > 10000) {
            throw new \InvalidArgumentException('Assiette ou taux de commission invalide.');
        }
        $this->id = Uuid::v7();
        $this->intervention = $intervention;
        $this->professional = $intervention->getProfessional();
        $this->baseMillimes = $baseMillimes;
        $this->rateBasisPoints = $rateBasisPoints;
        $this->amountMillimes = self::compute($baseMillimes, $rateBasisPoints);
    }

    /** Arrondi au millime le plus proche. */
    public static function compute(int $baseMillimes, int $rateBasisPoints): int
    {
        return intdiv($baseMillimes * $rateBasisPoints + 5000, 10000);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIntervention(): Intervention
    {
        return $this->intervention;
    }

    public function getProfessional(): ProfessionalProfile
    {
        return $this->professional;
    }

    public function getBaseMillimes(): int
    {
        return $this->baseMillimes;
    }

    public function getRateBasisPoints(): int
    {
        return $this->rateBasisPoints;
    }

    public function getAmountMillimes(): int
    {
        return $this->amountMillimes;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): CommissionStatus
    {
        return $this->status;
    }

    public function markPaid(\DateTimeImmutable $at): void
    {
        $this->status = CommissionStatus::Paid;
        $this->paidAt = $at;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }
}
