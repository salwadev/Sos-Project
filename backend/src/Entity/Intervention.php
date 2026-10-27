<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\ApiResource\Intervention\CreateReviewInput;
use App\ApiResource\Intervention\DisputeInterventionInput;
use App\ApiResource\Intervention\InterventionView;
use App\ApiResource\Intervention\ProposeQuoteInput;
use App\ApiResource\Intervention\UpdateInterventionStatusInput;
use App\Entity\Trait\TimestampableTrait;
use App\Enum\InterventionStatus;
use App\Enum\QuoteStatus;
use App\Enum\UserRole;
use App\Security\Voter\InterventionVoter;
use App\State\Intervention\ConfirmCashPaymentProcessor;
use App\State\Intervention\ConfirmCompletionProcessor;
use App\State\Intervention\CreateReviewProcessor;
use App\State\Intervention\DisputeInterventionProcessor;
use App\State\Intervention\InterventionProvider;
use App\State\Intervention\ProposeQuoteProcessor;
use App\State\Intervention\UpdateInterventionStatusProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Intervention née d'une offre confirmée par le client (§13.2).
 * Porte les étapes d'exécution (§6.6), les versions de devis, les paiements et la commission.
 *
 * API (§14.3, §14.4) : le professionnel assigné déclare les étapes et propose les devis ; le client propriétaire
 * de la demande accepte ou refuse les devis (/api/quotes/{id}/…), confirme la fin, conteste et laisse un avis ;
 * le professionnel confirme ensuite avoir reçu le paiement en espèces.
 * Toutes les réponses sont des InterventionView adaptées à celui qui consulte.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_intervention_professional_status', columns: ['professional_id', 'status'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/interventions',
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            output: InterventionView::class,
            provider: InterventionProvider::class,
        ),
        new Get(
            uriTemplate: '/interventions/{id}',
            security: "is_granted('ROLE_USER')",
            output: InterventionView::class,
            provider: InterventionProvider::class,
        ),
        new Post(
            uriTemplate: '/interventions/{id}/status',
            status: 200,
            throwOnNotFound: true,
            security: "is_granted('".InterventionVoter::WORK."', object)",
            input: UpdateInterventionStatusInput::class,
            output: InterventionView::class,
            processor: UpdateInterventionStatusProcessor::class,
        ),
        new Post(
            uriTemplate: '/interventions/{id}/quotes',
            status: 201,
            throwOnNotFound: true,
            security: "is_granted('".InterventionVoter::WORK."', object)",
            input: ProposeQuoteInput::class,
            output: InterventionView::class,
            processor: ProposeQuoteProcessor::class,
        ),
        new Post(
            uriTemplate: '/interventions/{id}/confirm-completion',
            status: 200,
            throwOnNotFound: true,
            security: "is_granted('".InterventionVoter::CLIENT."', object)",
            deserialize: false,
            output: InterventionView::class,
            processor: ConfirmCompletionProcessor::class,
        ),
        new Post(
            uriTemplate: '/interventions/{id}/payment/confirm-cash',
            status: 200,
            throwOnNotFound: true,
            security: "is_granted('".InterventionVoter::WORK."', object)",
            deserialize: false,
            output: InterventionView::class,
            processor: ConfirmCashPaymentProcessor::class,
        ),
        new Post(
            uriTemplate: '/interventions/{id}/dispute',
            status: 200,
            throwOnNotFound: true,
            security: "is_granted('".InterventionVoter::CLIENT."', object)",
            input: DisputeInterventionInput::class,
            output: InterventionView::class,
            processor: DisputeInterventionProcessor::class,
        ),
        new Post(
            uriTemplate: '/interventions/{id}/reviews',
            status: 201,
            throwOnNotFound: true,
            security: "is_granted('".InterventionVoter::CLIENT."', object)",
            input: CreateReviewInput::class,
            output: InterventionView::class,
            processor: CreateReviewProcessor::class,
        ),
    ],
)]
class Intervention
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ServiceRequest $request;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private RequestOffer $offer;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ProfessionalProfile $professional;

    #[ORM\Column(length: 24, enumType: InterventionStatus::class)]
    private InterventionStatus $status = InterventionStatus::Confirmed;

    /** Heure d'arrivée estimée — toujours présentée comme une estimation (§5.6). */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $estimatedArrivalAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $onTheWayAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $arrivedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $workStartedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completionDeclaredAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /** @var Collection<int, Quote> */
    #[ORM\OneToMany(targetEntity: Quote::class, mappedBy: 'intervention', cascade: ['persist'])]
    #[ORM\OrderBy(['version' => 'ASC'])]
    private Collection $quotes;

    public function __construct(ServiceRequest $request, RequestOffer $offer)
    {
        $this->id = Uuid::v7();
        $this->request = $request;
        $this->offer = $offer;
        $this->professional = $offer->getProfessional();
        $this->quotes = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRequest(): ServiceRequest
    {
        return $this->request;
    }

    public function getOffer(): RequestOffer
    {
        return $this->offer;
    }

    public function getProfessional(): ProfessionalProfile
    {
        return $this->professional;
    }

    public function isAssignedTo(User $user): bool
    {
        return $user->hasRole(UserRole::Professional)
            && $this->professional->getUser()->getId()->equals($user->getId());
    }

    public function getStatus(): InterventionStatus
    {
        return $this->status;
    }

    public function setStatus(InterventionStatus $status, \DateTimeImmutable $at): void
    {
        $this->status = $status;
        match ($status) {
            InterventionStatus::OnTheWay => $this->onTheWayAt = $at,
            InterventionStatus::OnSite => $this->arrivedAt = $at,
            InterventionStatus::Working => $this->workStartedAt = $at,
            InterventionStatus::CompletionDeclared => $this->completionDeclaredAt = $at,
            InterventionStatus::Completed => $this->completedAt = $at,
            default => null,
        };
    }

    public function getEstimatedArrivalAt(): ?\DateTimeImmutable
    {
        return $this->estimatedArrivalAt;
    }

    public function setEstimatedArrivalAt(?\DateTimeImmutable $at): void
    {
        $this->estimatedArrivalAt = $at;
    }

    public function getOnTheWayAt(): ?\DateTimeImmutable
    {
        return $this->onTheWayAt;
    }

    public function getArrivedAt(): ?\DateTimeImmutable
    {
        return $this->arrivedAt;
    }

    public function getWorkStartedAt(): ?\DateTimeImmutable
    {
        return $this->workStartedAt;
    }

    public function getCompletionDeclaredAt(): ?\DateTimeImmutable
    {
        return $this->completionDeclaredAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** @return Collection<int, Quote> */
    public function getQuotes(): Collection
    {
        return $this->quotes;
    }

    public function addQuote(Quote $quote): void
    {
        $this->quotes->add($quote);
    }

    /** Dernière version acceptée : c'est elle qui fait foi ; les précédentes restent conservées (§9.2, §16.4). */
    public function getAcceptedQuote(): ?Quote
    {
        return $this->latestQuoteIn(QuoteStatus::Accepted);
    }

    /** Version en attente de la décision du client (au plus une : une nouvelle proposition remplace l'ancienne). */
    public function getPendingQuote(): ?Quote
    {
        return $this->latestQuoteIn(QuoteStatus::Proposed);
    }

    private function latestQuoteIn(QuoteStatus $status): ?Quote
    {
        $latest = null;
        foreach ($this->quotes as $quote) {
            if ($status === $quote->getStatus() && (null === $latest || $quote->getVersion() > $latest->getVersion())) {
                $latest = $quote;
            }
        }

        return $latest;
    }
}
