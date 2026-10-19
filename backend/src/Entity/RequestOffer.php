<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\ApiResource\Offer\AcceptOfferInput;
use App\ApiResource\Offer\OfferView;
use App\Entity\Trait\TimestampableTrait;
use App\Enum\OfferStatus;
use App\Repository\RequestOfferRepository;
use App\Security\Voter\RequestOfferVoter;
use App\State\Offer\AcceptOfferProcessor;
use App\State\Offer\DeclineOfferProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Sollicitation d'un professionnel pour une demande (§6.4, §8.3).
 * « reserved » = le pro a accepté et la demande lui est réservée jusqu'à reservedUntil ;
 * l'exclusivité est garantie côté serveur par un verrou sur la demande (§6.5, §16.3).
 *
 * POST /api/offers/{id}/accept et /decline servent aux deux parties (§14.3, §14.4) : le professionnel
 * sollicité accepte ou décline ; le client propriétaire de la demande confirme ou refuse une offre réservée.
 */
#[ORM\Entity(repositoryClass: RequestOfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_offer_request_professional', columns: ['request_id', 'professional_id'])]
#[ORM\Index(name: 'idx_offer_professional_status', columns: ['professional_id', 'status'])]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/offers/{id}/accept',
            status: 200,
            throwOnNotFound: true,
            security: "is_granted('".RequestOfferVoter::RESPOND."', object)",
            input: AcceptOfferInput::class,
            output: OfferView::class,
            processor: AcceptOfferProcessor::class,
        ),
        new Post(
            uriTemplate: '/offers/{id}/decline',
            status: 200,
            throwOnNotFound: true,
            security: "is_granted('".RequestOfferVoter::RESPOND."', object)",
            deserialize: false,
            output: OfferView::class,
            processor: DeclineOfferProcessor::class,
        ),
    ],
)]
class RequestOffer
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ServiceRequest $request;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ProfessionalProfile $professional;

    #[ORM\Column(length: 16, enumType: OfferStatus::class)]
    private OfferStatus $status = OfferStatus::Sent;

    /** Vague de diffusion (1 = premier groupe de professionnels, §8.3). */
    #[ORM\Column]
    private int $wave;

    #[ORM\Column]
    private int $travelFeeMillimes;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    /** Délai de réponse du professionnel (§6.4). */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $respondBefore;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reservedUntil = null;

    public function __construct(
        ServiceRequest $request,
        ProfessionalProfile $professional,
        int $wave,
        int $travelFeeMillimes,
        \DateTimeImmutable $sentAt,
        \DateTimeImmutable $respondBefore,
    ) {
        $this->id = Uuid::v7();
        $this->request = $request;
        $this->professional = $professional;
        $this->wave = $wave;
        $this->travelFeeMillimes = $travelFeeMillimes;
        $this->sentAt = $sentAt;
        $this->respondBefore = $respondBefore;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRequest(): ServiceRequest
    {
        return $this->request;
    }

    public function getProfessional(): ProfessionalProfile
    {
        return $this->professional;
    }

    public function getStatus(): OfferStatus
    {
        return $this->status;
    }

    public function getWave(): int
    {
        return $this->wave;
    }

    public function getTravelFeeMillimes(): int
    {
        return $this->travelFeeMillimes;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getRespondBefore(): \DateTimeImmutable
    {
        return $this->respondBefore;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function getReservedUntil(): ?\DateTimeImmutable
    {
        return $this->reservedUntil;
    }

    public function reserve(\DateTimeImmutable $at, \DateTimeImmutable $until, int $travelFeeMillimes): void
    {
        $this->assertStatus(OfferStatus::Sent);
        $this->status = OfferStatus::Reserved;
        $this->respondedAt = $at;
        $this->reservedUntil = $until;
        $this->travelFeeMillimes = $travelFeeMillimes;
    }

    public function decline(\DateTimeImmutable $at): void
    {
        $this->assertStatus(OfferStatus::Sent);
        $this->status = OfferStatus::Declined;
        $this->respondedAt = $at;
    }

    public function expire(): void
    {
        $this->assertStatus(OfferStatus::Sent, OfferStatus::Reserved);
        $this->status = OfferStatus::Expired;
    }

    public function confirmByClient(): void
    {
        $this->assertStatus(OfferStatus::Reserved);
        $this->status = OfferStatus::Confirmed;
    }

    public function rejectByClient(): void
    {
        $this->assertStatus(OfferStatus::Reserved);
        $this->status = OfferStatus::Rejected;
    }

    private function assertStatus(OfferStatus ...$allowed): void
    {
        if (!\in_array($this->status, $allowed, true)) {
            throw new \DomainException(\sprintf('Offre en statut « %s » : action impossible.', $this->status->value));
        }
    }
}
