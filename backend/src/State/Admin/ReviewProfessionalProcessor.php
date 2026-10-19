<?php

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Admin\VerificationDecisionInput;
use App\ApiResource\Professional\ProfessionalProfileView;
use App\Domain\Professional\DocumentReview;
use App\Domain\Professional\ProfessionalVerifier;
use App\Domain\Professional\VerificationAction;
use App\Entity\User;
use App\Enum\DocumentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * PATCH /api/admin/professionals/{id}/verification (§7.2, §14.5) : décisions sur les justificatifs puis
 * sur le profil, sous verrou et journalisées (ProfessionalVerifier). 409 si l'état ne permet pas l'action.
 *
 * @implements ProcessorInterface<VerificationDecisionInput, ProfessionalProfileView>
 */
final class ReviewProfessionalProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ProfessionalVerifier $verifier,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfessionalProfileView
    {
        \assert($data instanceof VerificationDecisionInput);
        $admin = $this->security->getUser();
        \assert($admin instanceof User);

        $profile = AdminProfessionalProvider::find($this->em, $uriVariables['id'] ?? null) ?? throw new NotFoundHttpException();

        $reviews = [];
        foreach ($data->documents as $i => $input) {
            $document = $profile->findDocument((string) $input->id)
                ?? throw new UnprocessableEntityHttpException(\sprintf('documents[%d] : justificatif inconnu pour ce professionnel.', $i));
            $reviews[] = new DocumentReview(
                $document,
                DocumentStatus::from((string) $input->status),
                null === $input->rejectionReason ? null : trim($input->rejectionReason),
                null === $input->expiresAt ? null : new \DateTimeImmutable($input->expiresAt, new \DateTimeZone('UTC')),
            );
        }

        $this->verifier->decide(
            $profile,
            $admin,
            null === $data->action ? null : VerificationAction::from($data->action),
            trim((string) $data->reason),
            $reviews,
        );

        return ProfessionalProfileView::forAdmin($profile);
    }
}
