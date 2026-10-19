<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Profil client : prénom et adresses enregistrées (§4.1). */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class ClientProfile
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: 'clientProfile')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $firstName = null;

    /** @var Collection<int, ClientAddress> */
    #[ORM\OneToMany(targetEntity: ClientAddress::class, mappedBy: 'client', cascade: ['persist'], orphanRemoval: true)]
    private Collection $addresses;

    public function __construct(User $user)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->addresses = new ArrayCollection();
        $user->setClientProfile($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    /** @return Collection<int, ClientAddress> */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(ClientAddress $address): void
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
        }
    }
}
