<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Adresse enregistrée par un client, avec repère local (§5.3). Coordonnées privées : accès restreint (§13.4). */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class ClientAddress
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ClientProfile $client;

    #[ORM\Column(length: 60)]
    private string $label;

    #[ORM\Column(length: 255)]
    private string $addressLine;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $landmark = null;

    /** Point EWKT (SRID 4326). */
    #[ORM\Column(type: PostGISType::GEOGRAPHY, nullable: true, options: ['geometry_type' => 'POINT', 'srid' => 4326])]
    private ?string $location = null;

    public function __construct(ClientProfile $client, string $label, string $addressLine)
    {
        $this->id = Uuid::v7();
        $this->client = $client;
        $this->label = $label;
        $this->addressLine = $addressLine;
        $client->addAddress($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAddressLine(): string
    {
        return $this->addressLine;
    }

    public function getLandmark(): ?string
    {
        return $this->landmark;
    }

    public function setLandmark(?string $landmark): void
    {
        $this->landmark = $landmark;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }
}
