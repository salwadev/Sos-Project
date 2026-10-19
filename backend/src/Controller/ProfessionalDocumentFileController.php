<?php

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Entity\ProfessionalDocument;
use App\Entity\User;
use App\Security\Voter\ProfessionalDocumentVoter;
use App\Storage\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * GET /api/professional/documents/{id}/file : contenu d'un justificatif, au propriétaire et à l'administration
 * seulement (§16.6). Servi en pièce jointe, jamais mis en cache ; les accès de l'administration sont journalisés.
 */
final class ProfessionalDocumentFileController extends AbstractController
{
    private const EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('/api/professional/documents/{id}/file', name: 'professional_document_file', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id): StreamedResponse
    {
        $document = $this->em->find(ProfessionalDocument::class, $id)
            ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(ProfessionalDocumentVoter::VIEW, $document);

        $user = $this->getUser();
        \assert($user instanceof User);
        if (!$document->getProfessional()->getUser()->getId()->equals($user->getId())) {
            // Consultation par l'administration : tracée, sans rien du contenu (§16.7).
            $this->audit->record('professional.document_viewed', $user, 'ProfessionalDocument', $document->getId()->toRfc4122(), [
                'professional' => $document->getProfessional()->getId()->toRfc4122(),
                'type' => $document->getType()->value,
            ]);
        }

        $stream = $this->storage->read($document->getStorageKey());
        $response = new StreamedResponse(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        });
        $filename = \sprintf('%s-%s.%s', $document->getType()->value, $document->getId()->toRfc4122(), self::EXTENSIONS[$document->getMimeType()] ?? 'bin');
        $response->headers->set('Content-Type', $document->getMimeType());
        $response->headers->set('Content-Length', (string) $document->getSizeBytes());
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
