<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Catégorie de premier niveau : Maison, Automobile, Santé, Autres (§3.1).
 * « sensitive » marque le parcours santé, séparé et à données minimisées (§10).
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Category',
    operations: [
        new GetCollection(uriTemplate: '/categories', paginationEnabled: false, order: ['position' => 'ASC']),
        new Get(uriTemplate: '/categories/{id}'),
    ],
    normalizationContext: ['groups' => ['catalog:read']],
)]
class ServiceCategory
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
    private string $nameFr;

    #[ORM\Column(length: 80)]
    #[Groups(['catalog:read'])]
    private string $nameAr;

    #[ORM\Column]
    #[Groups(['catalog:read'])]
    private bool $sensitive = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, Service> */
    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'category')]
    private Collection $services;

    public function __construct(string $code, string $nameFr, string $nameAr, int $position = 0, bool $sensitive = false)
    {
        $this->id = Uuid::v7();
        $this->code = $code;
        $this->nameFr = $nameFr;
        $this->nameAr = $nameAr;
        $this->position = $position;
        $this->sensitive = $sensitive;
        $this->services = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /** @return Collection<int, Service> */
    public function getServices(): Collection
    {
        return $this->services;
    }
}
