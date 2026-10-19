<?php

namespace App\Domain\Professional;

use App\Entity\ProfessionalDocument;
use App\Enum\DocumentStatus;

/** Décision de l'administration sur un justificatif (§7.2). */
final class DocumentReview
{
    public function __construct(
        public readonly ProfessionalDocument $document,
        public readonly DocumentStatus $status,
        public readonly ?string $rejectionReason = null,
        public readonly ?\DateTimeImmutable $expiresAt = null,
    ) {
    }
}
