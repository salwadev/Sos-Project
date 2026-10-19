<?php

namespace App\ApiResource\Intervention;

use App\Entity\Review;
use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /api/interventions/{id}/reviews (§5.7). */
final class CreateReviewInput
{
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 5)]
    public ?int $rating = null;

    #[Assert\Length(max: Review::MAX_COMMENT_LENGTH)]
    public ?string $comment = null;
}
