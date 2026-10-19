<?php

namespace App\ApiResource\Professional;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Entity\ProfessionalDocument;
use App\State\Professional\UploadProfessionalDocumentProcessor;

/**
 * Justificatif tel qu'exposé par l'API : métadonnées seulement. La clé de stockage n'est jamais renvoyée ;
 * le fichier se lit par GET fileUrl, réservé au professionnel propriétaire et à l'administration (§16.6).
 */
#[ApiResource(
    shortName: 'ProfessionalDocument',
    operations: [
        new Post(
            uriTemplate: '/professional/documents',
            inputFormats: ['multipart' => ['multipart/form-data']],
            deserialize: false,
            validate: false,
            security: "is_granted('ROLE_PROFESSIONAL')",
            processor: UploadProfessionalDocumentProcessor::class,
            description: 'Dépôt d\'un justificatif en multipart/form-data : champs « file », « type » et « expiresAt » (AAAA-MM-JJ, facultatif).',
        ),
    ],
)]
final class ProfessionalDocumentView
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly string $id,
        public readonly string $type,
        public readonly string $status,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly ?string $expiresAt,
        public readonly \DateTimeImmutable $uploadedAt,
        public readonly ?\DateTimeImmutable $reviewedAt,
        public readonly ?string $rejectionReason,
        public readonly string $fileUrl,
    ) {
    }

    public static function of(ProfessionalDocument $document): self
    {
        return new self(...self::describe($document));
    }

    /** @return array<string, mixed> mêmes champs, pour l'imbriquer dans la vue du profil */
    public static function describe(ProfessionalDocument $document): array
    {
        $id = $document->getId()->toRfc4122();

        return [
            'id' => $id,
            'type' => $document->getType()->value,
            'status' => $document->getStatus()->value,
            'mimeType' => $document->getMimeType(),
            'sizeBytes' => $document->getSizeBytes(),
            'expiresAt' => $document->getExpiresAt()?->format('Y-m-d'),
            'uploadedAt' => $document->getCreatedAt(),
            'reviewedAt' => $document->getReviewedAt(),
            'rejectionReason' => $document->getRejectionReason(),
            'fileUrl' => \sprintf('/api/professional/documents/%s/file', $id),
        ];
    }
}
