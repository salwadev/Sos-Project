<?php

namespace App\ApiResource\Professional;

use App\Enum\DocumentType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Champs multipart/form-data de POST /api/professional/documents (§6.2, §12.6). */
final class UploadProfessionalDocumentInput
{
    public const MAX_SIZE = '10Mi';
    /** Type réel détecté sur le contenu du fichier, pas l'en-tête ni l'extension envoyés par le client. */
    public const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /** Valeur de DocumentType : identity, qualification, company_registration, insurance, other. */
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [DocumentType::class, 'values'])]
    public ?string $type = null;

    #[Assert\NotNull(message: 'Fichier manquant (champ « file »).')]
    #[Assert\File(maxSize: self::MAX_SIZE)]
    public ?UploadedFile $file = null;

    /** Date d'expiration du justificatif (AAAA-MM-JJ), le cas échéant (§7.2). */
    #[Assert\Date]
    public ?string $expiresAt = null;

    /** Type MIME lu dans le contenu du fichier (extension fileinfo), ou null sans fichier lisible. */
    public function detectedMimeType(): ?string
    {
        $path = $this->file?->getPathname();
        if (null === $path || '' === $path || !is_file($path)) {
            return null;
        }
        $mime = (new \finfo(\FILEINFO_MIME_TYPE))->file($path);

        return false === $mime ? null : $mime;
    }

    #[Assert\Callback]
    public function validateContentType(ExecutionContextInterface $context): void
    {
        if (null !== $this->file && $this->file->isValid() && !\in_array($this->detectedMimeType(), self::MIME_TYPES, true)) {
            $context->buildViolation('Formats acceptés : PDF, JPEG, PNG.')->atPath('file')->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateExpiry(ExecutionContextInterface $context): void
    {
        $date = null === $this->expiresAt ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $this->expiresAt, new \DateTimeZone('UTC'));
        if (false !== $date && $date < new \DateTimeImmutable('today', new \DateTimeZone('UTC'))) {
            $context->buildViolation('Ce justificatif est déjà expiré.')->atPath('expiresAt')->addViolation();
        }
    }
}
