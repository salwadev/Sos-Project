<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Service proposé par un professionnel, avec ses tarifs connus à l'avance (§6.1, §9.1). */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_professional_service', columns: ['professional_id', 'service_id'])]
class ProfessionalService
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProfessionalProfile $professional;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Service $service;

    /** Frais de déplacement en millimes. */
    #[ORM\Column]
    private int $travelFeeMillimes;

    /** Prix du diagnostic en millimes, si facturé séparément. */
    #[ORM\Column(nullable: true)]
    private ?int $diagnosticFeeMillimes = null;

    public function __construct(ProfessionalProfile $professional, Service $service, int $travelFeeMillimes)
    {
        $this->id = Uuid::v7();
        $this->professional = $professional;
        $this->service = $service;
        $this->setTravelFeeMillimes($travelFeeMillimes);
        $professional->addService($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProfessional(): ProfessionalProfile
    {
        return $this->professional;
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function getTravelFeeMillimes(): int
    {
        return $this->travelFeeMillimes;
    }

    public function setTravelFeeMillimes(int $millimes): void
    {
        if ($millimes < 0) {
            throw new \InvalidArgumentException('Les frais ne peuvent pas être négatifs.');
        }
        $this->travelFeeMillimes = $millimes;
    }

    public function getDiagnosticFeeMillimes(): ?int
    {
        return $this->diagnosticFeeMillimes;
    }

    public function setDiagnosticFeeMillimes(?int $millimes): void
    {
        $this->diagnosticFeeMillimes = $millimes;
    }
}
