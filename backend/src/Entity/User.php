<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Compte identifié par son numéro de téléphone (connexion OTP, §5.1).
 * Un utilisateur peut cumuler plusieurs rôles (§13.2) : client, professionnel, support, admin.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** Numéro au format E.164, ex. +21622123456. */
    #[ORM\Column(length: 16, unique: true)]
    private string $phone;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(length: 16, enumType: UserStatus::class)]
    private UserStatus $status = UserStatus::Active;

    #[ORM\Column(length: 2)]
    private string $locale = 'fr';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist'])]
    private ?ClientProfile $clientProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist'])]
    private ?ProfessionalProfile $professionalProfile = null;

    public function __construct(string $phone, UserRole ...$roles)
    {
        $this->id = Uuid::v7();
        $this->phone = $phone;
        foreach ($roles as $role) {
            $this->addRole($role);
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getUserIdentifier(): string
    {
        return $this->id->toRfc4122();
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function hasRole(UserRole $role): bool
    {
        return \in_array($role->value, $this->roles, true);
    }

    public function addRole(UserRole $role): void
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role->value;
        }
    }

    public function removeRole(UserRole $role): void
    {
        $this->roles = array_values(array_filter($this->roles, static fn (string $r) => $r !== $role->value));
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Aucun secret n'est stocké sur l'utilisateur (authentification par OTP).
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): void
    {
        $this->status = $status;
    }

    public function isActive(): bool
    {
        return UserStatus::Active === $this->status;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function markLoggedIn(\DateTimeImmutable $at): void
    {
        $this->lastLoginAt = $at;
    }

    public function getClientProfile(): ?ClientProfile
    {
        return $this->clientProfile;
    }

    public function setClientProfile(ClientProfile $profile): void
    {
        $this->clientProfile = $profile;
    }

    public function getProfessionalProfile(): ?ProfessionalProfile
    {
        return $this->professionalProfile;
    }

    public function setProfessionalProfile(ProfessionalProfile $profile): void
    {
        $this->professionalProfile = $profile;
    }
}
