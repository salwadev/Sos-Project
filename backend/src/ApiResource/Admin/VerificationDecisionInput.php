<?php

namespace App\ApiResource\Admin;

use App\Domain\Professional\VerificationAction;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Corps de PATCH /api/admin/professionals/{id}/verification (application/merge-patch+json, §7.2, §14.5).
 * Les décisions sur les justificatifs sont appliquées d'abord, puis l'action sur le profil :
 * une même requête peut donc valider la pièce d'identité et approuver le profil.
 */
final class VerificationDecisionInput
{
    /** approve (pending|rejected → verified), reject (pending → rejected), suspend (verified → suspended), reactivate (suspended → verified). */
    #[Assert\Choice(callback: [VerificationAction::class, 'values'])]
    public ?string $action = null;

    /** Motif de la décision, toujours obligatoire : conservé sur le profil et dans le journal d'audit. */
    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    public ?string $reason = null;

    /** @var list<DocumentReviewInput> */
    #[Assert\Count(max: 20)]
    #[Assert\Valid]
    public array $documents = [];

    #[Assert\Callback]
    public function validateDecision(ExecutionContextInterface $context): void
    {
        if (null === $this->action && [] === $this->documents) {
            $context->buildViolation('Indiquer une action sur le profil ou au moins un justificatif.')->atPath('action')->addViolation();
        }
        $ids = array_map(static fn ($d) => $d instanceof DocumentReviewInput ? strtolower((string) $d->id) : null, $this->documents);
        if (\count($ids) !== \count(array_unique($ids))) {
            $context->buildViolation('Justificatif en double.')->atPath('documents')->addViolation();
        }
    }
}
