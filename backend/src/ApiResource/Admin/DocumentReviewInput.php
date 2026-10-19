<?php

namespace App\ApiResource\Admin;

use App\Enum\DocumentStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Décision sur un justificatif dans PATCH /api/admin/professionals/{id}/verification (§6.2, §7.2). */
final class DocumentReviewInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $id = null;

    /** « approved » ou « rejected ». */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [DocumentStatus::Approved->value, DocumentStatus::Rejected->value])]
    public ?string $status = null;

    /** Obligatoire en cas de rejet ; communiqué au professionnel. */
    #[Assert\Length(max: 500)]
    public ?string $rejectionReason = null;

    /** Date d'expiration fixée par l'administration (AAAA-MM-JJ) ; remplace celle déclarée au dépôt (§7.2). */
    #[Assert\Date]
    public ?string $expiresAt = null;

    #[Assert\Callback]
    public function validateReason(ExecutionContextInterface $context): void
    {
        if (DocumentStatus::Rejected->value === $this->status && '' === trim((string) $this->rejectionReason)) {
            $context->buildViolation('Motif de rejet obligatoire.')->atPath('rejectionReason')->addViolation();
        }
    }
}
