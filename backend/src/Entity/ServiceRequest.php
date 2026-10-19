<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\ApiResource\Request\CancelServiceRequestInput;
use App\ApiResource\Request\CreateServiceRequestInput;
use App\Entity\Trait\TimestampableTrait;
use App\Enum\ServiceRequestStatus;
use App\Enum\Urgency;
use App\Repository\ServiceRequestRepository;
use App\Security\Voter\ServiceRequestVoter;
use App\State\Request\CancelServiceRequestProcessor;
use App\State\Request\CreateServiceRequestProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jsor\Doctrine\PostGIS\Types\PostGISType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Demande d'intervention d'un client (§5.3).
 * Le statut n'est modifié que par la machine à états `service_request` (config/packages/workflow.yaml, §8.4).
 */
#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_request_status_created', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_request_service_area', columns: ['service_id', 'area_id'])]
#[ORM\Index(name: 'idx_request_location', columns: ['location'], flags: ['spatial'])]
#[ApiResource(
    shortName: 'Request',
    operations: [
        new GetCollection(
            uriTemplate: '/requests',
            order: ['createdAt' => 'DESC'],
            security: "is_granted('ROLE_USER')",
        ),
        new Get(
            uriTemplate: '/requests/{id}',
            security: "is_granted('".ServiceRequestVoter::VIEW."', object)",
        ),
        new Post(
            uriTemplate: '/requests',
            security: "is_granted('ROLE_CLIENT')",
            input: CreateServiceRequestInput::class,
            processor: CreateServiceRequestProcessor::class,
        ),
        new Post(
            uriTemplate: '/requests/{id}/cancel',
            status: 200,
            security: "is_granted('".ServiceRequestVoter::CANCEL."', object)",
            input: CancelServiceRequestInput::class,
            processor: CancelServiceRequestProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['request:read']],
)]
class ServiceRequest
{
    use TimestampableTrait;

    private const REFERENCE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['request:read'])]
    private Uuid $id;

    /** Référence lisible communiquée au client et au support, ex. UT-7K4M2Q. */
    #[ORM\Column(length: 12, unique: true)]
    #[Groups(['request:read'])]
    private string $reference;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $client;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['request:read'])]
    private Service $service;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['request:read'])]
    private ?ServiceArea $area = null;

    /** Marquage de la machine à états ; valeurs de ServiceRequestStatus. */
    #[ORM\Column(length: 20)]
    private string $status = ServiceRequestStatus::Draft->value;

    #[ORM\Column(length: 10, enumType: Urgency::class)]
    #[Groups(['request:read'])]
    private Urgency $urgency;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['request:read'])]
    private string $description;

    #[ORM\Column(length: 255)]
    #[Groups(['request:read'])]
    private string $addressLine;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['request:read'])]
    private ?string $landmark = null;

    /** Position exacte (EWKT) : révélée au professionnel seulement après confirmation (§6.4). */
    #[ORM\Column(type: PostGISType::GEOGRAPHY, nullable: true, options: ['geometry_type' => 'POINT', 'srid' => 4326])]
    private ?string $location = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['request:read'])]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['request:read'])]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['request:read'])]
    private ?string $cancelReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    /** Clé fournie par le client pour rejouer une création sans doublon (§8.6, §15.5). */
    #[ORM\Column(length: 80, unique: true, nullable: true)]
    private ?string $idempotencyKey = null;

    /** Identifiant de corrélation propagé dans les journaux et notifications (§13.3). */
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $correlationId;

    #[ORM\Column(length: 16)]
    private string $source = 'mobile';

    /** @var Collection<int, RequestOffer> */
    #[ORM\OneToMany(targetEntity: RequestOffer::class, mappedBy: 'request')]
    private Collection $offers;

    /** @var Collection<int, ServiceRequestTransition> */
    #[ORM\OneToMany(targetEntity: ServiceRequestTransition::class, mappedBy: 'request', cascade: ['persist'])]
    #[ORM\OrderBy(['occurredAt' => 'ASC'])]
    private Collection $transitions;

    public function __construct(User $client, Service $service, Urgency $urgency, string $description, string $addressLine)
    {
        $this->id = Uuid::v7();
        $this->reference = self::generateReference();
        $this->correlationId = Uuid::v4();
        $this->client = $client;
        $this->service = $service;
        $this->urgency = $urgency;
        $this->description = $description;
        $this->addressLine = $addressLine;
        $this->offers = new ArrayCollection();
        $this->transitions = new ArrayCollection();
    }

    public static function generateReference(): string
    {
        $suffix = '';
        for ($i = 0; $i < 6; ++$i) {
            $suffix .= self::REFERENCE_ALPHABET[random_int(0, \strlen(self::REFERENCE_ALPHABET) - 1)];
        }

        return 'UT-'.$suffix;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getClient(): User
    {
        return $this->client;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->client->getId()->equals($user->getId());
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function isSensitive(): bool
    {
        return $this->service->getCategory()->isSensitive();
    }

    public function getArea(): ?ServiceArea
    {
        return $this->area;
    }

    public function setArea(?ServiceArea $area): void
    {
        $this->area = $area;
    }

    /** Utilisé par le marking store du workflow : ne pas appeler directement. */
    #[Groups(['request:read'])]
    public function getStatus(): string
    {
        return $this->status;
    }

    /** @param array<string, mixed> $context */
    public function setStatus(string $status, array $context = []): void
    {
        $this->status = ServiceRequestStatus::from($status)->value;
    }

    public function getStatusEnum(): ServiceRequestStatus
    {
        return ServiceRequestStatus::from($this->status);
    }

    public function getUrgency(): Urgency
    {
        return $this->urgency;
    }

    public function getDescription(): string
    {
        return $this->description;
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

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function markSubmitted(\DateTimeImmutable $at): void
    {
        $this->submittedAt = $at;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function markClosed(\DateTimeImmutable $at): void
    {
        $this->closedAt = $at;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function recordCancellation(User $by, ?string $reason): void
    {
        $this->cancelledBy = $by;
        $this->cancelReason = $reason;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): void
    {
        $this->idempotencyKey = $idempotencyKey;
    }

    public function getCorrelationId(): Uuid
    {
        return $this->correlationId;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /** @return Collection<int, RequestOffer> */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    /** @return Collection<int, ServiceRequestTransition> */
    public function getTransitions(): Collection
    {
        return $this->transitions;
    }

    public function addTransition(ServiceRequestTransition $transition): void
    {
        $this->transitions->add($transition);
    }

    #[Groups(['request:read'])]
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
