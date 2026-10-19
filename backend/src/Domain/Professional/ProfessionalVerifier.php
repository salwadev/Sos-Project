<?php

namespace App\Domain\Professional;

use App\Audit\AuditLogger;
use App\Domain\Clock;
use App\Entity\ProfessionalDocument;
use App\Entity\ProfessionalProfile;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vérification d'un professionnel par l'administration (§6.2, §7.2).
 *
 * Tout se passe sous verrou de la ligne du profil (deux administrateurs ne décident jamais en même temps)
 * et dans une seule transaction avec le journal d'audit : soit la décision et sa trace sont écrites, soit rien.
 * Approuver ou réactiver exige une pièce d'identité approuvée et valide, et l'extrait du registre pour une
 * entreprise. Les qualifications sont exigées service par service au moment du matching (§8.1).
 */
final class ProfessionalVerifier
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param list<DocumentReview> $documentReviews justificatifs de ce profil, appliqués avant l'action
     */
    public function decide(ProfessionalProfile $profile, User $admin, ?VerificationAction $action, string $reason, array $documentReviews): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->em->refresh($profile, LockMode::PESSIMISTIC_WRITE);
            $now = Clock::now();
            $from = $profile->getVerificationStatus();
            if (null !== $action && !\in_array($from, $action->allowedFrom(), true)) {
                throw new InvalidVerificationChange(\sprintf('Action « %s » impossible : le profil est « %s ».', $action->value, $from->value));
            }

            foreach ($documentReviews as $review) {
                $this->review($review, $admin, $reason, $now);
            }

            if (null !== $action) {
                if (\in_array($action, [VerificationAction::Approve, VerificationAction::Reactivate], true)) {
                    $this->assertRequiredDocuments($profile, $now);
                }
                $profile->setVerificationStatus($action->target(), $reason, $now);
                if (VerificationAction::Suspend === $action) {
                    $profile->getAvailability()?->goUnavailable();
                }
                $this->em->flush();
                $this->audit->record('professional.verification_changed', $admin, 'ProfessionalProfile', $profile->getId()->toRfc4122(), [
                    'action' => $action->value,
                    'from' => $from->value,
                    'to' => $action->target()->value,
                    'reason' => $reason,
                ]);
            }

            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function review(DocumentReview $review, User $admin, string $reason, \DateTimeImmutable $now): void
    {
        $document = $review->document;
        $this->em->refresh($document);
        $previous = $document->getStatus();
        if (null !== $review->expiresAt) {
            $document->setExpiresAt($review->expiresAt);
        }
        if (DocumentStatus::Approved === $review->status && null !== $document->getExpiresAt() && $document->getExpiresAt() < $now->setTime(0, 0)) {
            throw new InvalidVerificationChange(\sprintf('Justificatif %s expiré : il ne peut pas être approuvé.', $document->getId()->toRfc4122()));
        }
        $document->review($review->status, $admin, $now, $review->rejectionReason);
        $this->em->flush();

        $this->audit->record('professional.document_reviewed', $admin, 'ProfessionalDocument', $document->getId()->toRfc4122(), [
            'professional' => $document->getProfessional()->getId()->toRfc4122(),
            'type' => $document->getType()->value,
            'from' => $previous->value,
            'to' => $document->getStatus()->value,
            'expiresAt' => $document->getExpiresAt()?->format('Y-m-d'),
            'reason' => $review->rejectionReason ?? $reason,
        ]);
    }

    private function assertRequiredDocuments(ProfessionalProfile $profile, \DateTimeImmutable $now): void
    {
        if (!$profile->hasValidDocument(DocumentType::Identity, $now)) {
            throw new InvalidVerificationChange('Pièce d\'identité approuvée et en cours de validité requise.');
        }
        if (ProfessionalProfile::LEGAL_COMPANY === $profile->getLegalStatus()
            && !$profile->hasValidDocument(DocumentType::CompanyRegistration, $now)) {
            throw new InvalidVerificationChange('Extrait du registre des entreprises approuvé et en cours de validité requis.');
        }
    }
}
