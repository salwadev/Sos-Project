<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Zone du Grand Tunis activable par l'administration (§1.3, §3.2).
 * Une zone n'est « active » que si une couverture réelle existe.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_service_area_boundary', columns: ['boundary'], flags: ['spatial'])]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/service-areas', paginationEnabled: false),
        new Get(uriTemplate: '/service-areas/{id}'),
    ],
    normalizationContext: ['groups' => ['catalog:read']],
)]
class ServiceArea
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['catalog:read'])]
    private Uuid $id;

    #[ORM\Column(length: 40, unique: true)]
    #[Groups(['catalog:read'])]
    private string $code;

    #[ORM\Column(length: 80)]
    #[Groups(['catalog:read'])]
    private string $name;

    /** Contour de la zone (EWKT MULTIPOLYGON, SRID 4326). */
    #[ORM\Column(type: PostGISType::GEOGRAPHY, options: ['geometry_type' => 'MULTIPOLYGON', 'srid' => 4326])]
    private string $boundary;

    #[ORM\Column]
    private bool $active = false;

    public function __construct(string $code, string $name, string $boundary)
    {
        $this->id = Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->boundary = $boundary;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBoundary(): string
    {
        return $this->boundary;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
