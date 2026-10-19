<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Disponibilité courante d'un professionnel (§6.3).
 * Elle expire : un professionnel n'est « disponible » que jusqu'à availableUntil et doit la reconfirmer.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_availability_window', columns: ['available', 'available_until'])]
#[ORM\Index(name: 'idx_availability_location', columns: ['current_location'], flags: ['spatial'])]
class Availability
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: 'availability')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ProfessionalProfile $professional;

    #[ORM\Column]
    private bool $available = false;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $availableUntil = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastConfirmedAt = null;

    /** Position récente (EWKT), facultative et non exposée au client avant confirmation (§6.4). */
    #[ORM\Column(type: PostGISType::GEOGRAPHY, nullable: true, options: ['geometry_type' => 'POINT', 'srid' => 4326])]
    private ?string $currentLocation = null;

    public function __construct(ProfessionalProfile $professional)
    {
        $this->id = Uuid::v7();
        $this->professional = $professional;
        $professional->setAvailability($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProfessional(): ProfessionalProfile
    {
        return $this->professional;
    }

    public function goAvailable(\DateTimeImmutable $now, \DateTimeImmutable $until): void
    {
        if ($until <= $now) {
            throw new \InvalidArgumentException('La fin de disponibilité doit être dans le futur.');
        }
        $this->available = true;
        $this->availableUntil = $until;
        $this->lastConfirmedAt = $now;
    }

    public function goUnavailable(): void
    {
        $this->available = false;
        $this->availableUntil = null;
    }

    public function isAvailableAt(\DateTimeImmutable $at): bool
    {
        return $this->available && null !== $this->availableUntil && $this->availableUntil > $at;
    }

    public function getAvailableUntil(): ?\DateTimeImmutable
    {
        return $this->availableUntil;
    }

    public function getLastConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->lastConfirmedAt;
    }

    public function getCurrentLocation(): ?string
    {
        return $this->currentLocation;
    }

    public function setCurrentLocation(?string $currentLocation): void
    {
        $this->currentLocation = $currentLocation;
    }
}
