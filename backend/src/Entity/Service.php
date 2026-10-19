<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/** Service précis d'une catégorie : plomberie, batterie, dentiste de garde… (§3.1). */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/services', paginationEnabled: false),
        new Get(uriTemplate: '/services/{id}'),
    ],
    normalizationContext: ['groups' => ['catalog:read']],
)]
class Service
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['catalog:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['catalog:read'])]
    private ServiceCategory $category;

    #[ORM\Column(length: 60, unique: true)]
    #[Groups(['catalog:read'])]
    private string $code;

    #[ORM\Column(length: 80)]
    #[Groups(['catalog:read'])]
    private string $nameFr;

    #[ORM\Column(length: 80)]
    #[Groups(['catalog:read'])]
    private string $nameAr;

    /** Métier réglementé : justificatif de qualification obligatoire pour être sollicité (§8.1, §10.3). */
    #[ORM\Column]
    private bool $requiresQualification = false;

    /** Frais de déplacement indicatifs en millimes (1 TND = 1000 millimes). */
    #[ORM\Column(nullable: true)]
    #[Groups(['catalog:read'])]
    private ?int $defaultTravelFeeMillimes = null;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(ServiceCategory $category, string $code, string $nameFr, string $nameAr)
    {
        $this->id = Uuid::v7();
        $this->category = $category;
        $this->code = $code;
        $this->nameFr = $nameFr;
        $this->nameAr = $nameAr;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCategory(): ServiceCategory
    {
        return $this->category;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getNameFr(): string
    {
        return $this->nameFr;
    }

    public function getNameAr(): string
    {
        return $this->nameAr;
    }

    public function requiresQualification(): bool
    {
        return $this->requiresQualification;
    }

    public function setRequiresQualification(bool $requiresQualification): void
    {
        $this->requiresQualification = $requiresQualification;
    }

    public function getDefaultTravelFeeMillimes(): ?int
    {
        return $this->defaultTravelFeeMillimes;
    }

    public function setDefaultTravelFeeMillimes(?int $millimes): void
    {
        $this->defaultTravelFeeMillimes = $millimes;
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
