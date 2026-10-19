<?php

namespace App\State\Professional;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\ApiResource\Professional\ProfessionalDocumentView;
use App\ApiResource\Professional\UploadProfessionalDocumentInput;
use App\Audit\AuditLogger;
use App\Entity\ProfessionalDocument;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Storage\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * POST /api/professional/documents : dépôt d'un justificatif, statut « pending » jusqu'à son examen
 * par l'administration (§6.2, §7.2). Le fichier part dans le stockage privé sous une clé générée ;
 * son nom d'origine n'est pas conservé, son type est déterminé d'après son contenu.
 *
 * @implements ProcessorInterface<null, ProfessionalDocumentView>
 */
final class UploadProfessionalDocumentProcessor implements ProcessorInterface
{
    /** Au-delà, le support doit faire le ménage : évite de remplir le stockage avec un seul compte. */
    private const MAX_DOCUMENTS_PER_PROFESSIONAL = 50;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
        private readonly DocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfessionalDocumentView
    {
        $user = $this->security->getUser();
        $professional = $user instanceof User ? $user->getProfessionalProfile() : null;
        if (null === $professional) {
            throw new AccessDeniedHttpException('Profil professionnel requis.');
        }
        \assert($user instanceof User);

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);
        $input = new UploadProfessionalDocumentInput();
        $input->type = self::field($request, 'type');
        $input->expiresAt = self::field($request, 'expiresAt');
        $file = $request->files->get('file');
        $input->file = $file instanceof UploadedFile ? $file : null;
        $this->validator->validate($input);
        \assert(null !== $input->file);

        if ($professional->getDocuments()->count() >= self::MAX_DOCUMENTS_PER_PROFESSIONAL) {
            throw new ConflictHttpException('Nombre maximal de justificatifs atteint : contactez le support.');
        }

        $key = \sprintf('professionals/%s/%s', $professional->getId()->toRfc4122(), Uuid::v4()->toRfc4122());
        $this->storage->put($key, $input->file->getPathname());
        try {
            $document = new ProfessionalDocument(
                $professional,
                DocumentType::from($input->type),
                $key,
                (string) $input->detectedMimeType(),
                (int) $input->file->getSize(),
            );
            $document->setExpiresAt(null === $input->expiresAt ? null : new \DateTimeImmutable($input->expiresAt, new \DateTimeZone('UTC')));
            $this->em->persist($document);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->storage->delete($key);
            throw $e;
        }

        $this->audit->record('professional.document_uploaded', $user, 'ProfessionalDocument', $document->getId()->toRfc4122(), [
            'professional' => $professional->getId()->toRfc4122(),
            'type' => $document->getType()->value,
            'mimeType' => $document->getMimeType(),
            'sizeBytes' => $document->getSizeBytes(),
        ]);

        return ProfessionalDocumentView::of($document);
    }

    private static function field(Request $request, string $name): ?string
    {
        $value = $request->request->get($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
