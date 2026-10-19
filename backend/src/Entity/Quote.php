<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\ApiResource\Intervention\InterventionView;
use App\Enum\QuoteStatus;
use App\Security\Voter\InterventionVoter;
use App\State\Intervention\DecideQuoteProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Version de devis (§9.2) : une hausse après diagnostic crée une nouvelle version,
 * qui doit être acceptée explicitement par le client avant tout travail (§6.6, §16.4).
 * Une version acceptée n'est plus modifiable.
 *
 * POST /api/quotes/{id}/accept et /reject : décision du client propriétaire de la demande ;
 * les versions sont proposées par POST /api/interventions/{id}/quotes.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_quote_version', columns: ['intervention_id', 'version'])]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/quotes/{id}/accept',
            status: 200,
            throwOnNotFound: true,
            security: "is_granted('".InterventionVoter::CLIENT."', object.getIntervention())",
            deserialize: false,
            output: InterventionView::class,
            name: DecideQuoteProcessor::ACCEPT,
            processor: DecideQuoteProcessor::class,
        ),
        new Post(
            uriTemplate: '/quotes/{id}/reject',
            status: 200,
            throwOnNotFound: true,
            security: "is_granted('".InterventionVoter::CLIENT."', object.getIntervention())",
            deserialize: false,
            output: InterventionView::class,
            name: DecideQuoteProcessor::REJECT,
            processor: DecideQuoteProcessor::class,
        ),
    ],
)]
class Quote
{
    public const CURRENCY = 'TND';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Intervention $intervention;

    #[ORM\Column]
    private int $version;

    #[ORM\Column(length: 16, enumType: QuoteStatus::class)]
    private QuoteStatus $status = QuoteStatus::Proposed;

    /** @var list<array{label: string, amountMillimes: int}> */
    #[ORM\Column(type: Types::JSON)]
    private array $lines;

    #[ORM\Column]
    private int $totalMillimes;

    #[ORM\Column(length: 3)]
    private string $currency = self::CURRENCY;

    /** Justification obligatoire d'une hausse après diagnostic (§6.6). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $explanation;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $proposedAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    /** @param list<array{label: string, amountMillimes: int}> $lines */
    public function __construct(Intervention $intervention, int $version, array $lines, \DateTimeImmutable $proposedAt, ?string $explanation = null)
    {
        if ([] === $lines) {
            throw new \InvalidArgumentException('Un devis comporte au moins une ligne.');
        }
        foreach ($lines as $line) {
            if ($line['amountMillimes'] < 0) {
                throw new \InvalidArgumentException('Montant de ligne négatif.');
            }
        }
        if ($version > 1 && (null === $explanation || '' === trim($explanation))) {
            throw new \InvalidArgumentException('Une nouvelle version de devis doit être justifiée.');
        }

        $this->id = Uuid::v7();
        $this->intervention = $intervention;
        $this->version = $version;
        $this->lines = $lines;
        $this->totalMillimes = array_sum(array_column($lines, 'amountMillimes'));
        $this->proposedAt = $proposedAt;
        $this->explanation = $explanation;
        $intervention->addQuote($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIntervention(): Intervention
    {
        return $this->intervention;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getStatus(): QuoteStatus
    {
        return $this->status;
    }

    public function isAccepted(): bool
    {
        return QuoteStatus::Accepted === $this->status;
    }

    /** @return list<array{label: string, amountMillimes: int}> */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getTotalMillimes(): int
    {
        return $this->totalMillimes;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function getProposedAt(): \DateTimeImmutable
    {
        return $this->proposedAt;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function accept(\DateTimeImmutable $at): void
    {
        $this->decide(QuoteStatus::Accepted, $at);
    }

    public function reject(\DateTimeImmutable $at): void
    {
        $this->decide(QuoteStatus::Rejected, $at);
    }

    public function supersede(): void
    {
        if (QuoteStatus::Proposed !== $this->status) {
            throw new \DomainException('Seul un devis en attente peut être remplacé.');
        }
        $this->status = QuoteStatus::Superseded;
    }

    private function decide(QuoteStatus $status, \DateTimeImmutable $at): void
    {
        if (QuoteStatus::Proposed !== $this->status) {
            throw new \DomainException('Ce devis a déjà été traité.');
        }
        $this->status = $status;
        $this->decidedAt = $at;
    }
}
