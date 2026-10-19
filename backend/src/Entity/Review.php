<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\ReviewStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Avis lié à une intervention terminée, un seul par intervention (§5.7). */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_review_professional_status', columns: ['professional_id', 'status'])]
class Review
{
    use TimestampableTrait;

    public const MAX_COMMENT_LENGTH = 500;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Intervention $intervention;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ProfessionalProfile $professional;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rating;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment;

    #[ORM\Column(length: 16, enumType: ReviewStatus::class)]
    private ReviewStatus $status = ReviewStatus::Published;

    public function __construct(Intervention $intervention, User $author, int $rating, ?string $comment)
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('La note doit être comprise entre 1 et 5.');
        }
        if (null !== $comment && mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            throw new \InvalidArgumentException('Commentaire trop long.');
        }
        $this->id = Uuid::v7();
        $this->intervention = $intervention;
        $this->author = $author;
        $this->professional = $intervention->getProfessional();
        $this->rating = $rating;
        $this->comment = $comment;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIntervention(): Intervention
    {
        return $this->intervention;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getProfessional(): ProfessionalProfile
    {
        return $this->professional;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getStatus(): ReviewStatus
    {
        return $this->status;
    }

    public function setStatus(ReviewStatus $status): void
    {
        $this->status = $status;
    }
}
