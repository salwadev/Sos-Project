<?php

namespace App\Intervention;

use App\Audit\AuditLogger;
use App\Entity\Intervention;
use App\Entity\Review;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\InterventionStatus;
use App\Enum\ServiceRequestStatus;
use App\Matching\RequestLock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Avis du client (§5.5, §5.7) : seulement pour une intervention éligible — terminée et confirmée par le client,
 * sans contestation en cours — et au plus un par intervention (contrainte unique en base en dernier recours).
 * La note moyenne du professionnel est mise à jour sous verrou de sa ligne.
 */
final class ReviewPublisher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestLock $lock,
        private readonly AuditLogger $audit,
    ) {
    }

    public function publish(Intervention $intervention, User $client, int $rating, ?string $comment): Review
    {
        $review = $this->lock->run($intervention->getRequest(), function (ServiceRequest $request) use ($intervention, $client, $rating, $comment): Review {
            $this->em->refresh($intervention);
            if (ServiceRequestStatus::Completed !== $request->getStatusEnum() || InterventionStatus::Completed !== $intervention->getStatus()) {
                throw new InterventionConflict('Un avis ne peut être laissé que sur une intervention terminée et confirmée.');
            }
            if (null !== $this->em->getRepository(Review::class)->findOneBy(['intervention' => $intervention])) {
                throw new InterventionConflict('Vous avez déjà laissé un avis sur cette intervention.');
            }

            $review = new Review($intervention, $client, $rating, null === $comment || '' === trim($comment) ? null : trim($comment));
            $this->em->persist($review);
            // Verrou de la seule ligne du profil (un refresh verrouillant joindrait des relations optionnelles,
            // ce que PostgreSQL refuse avec FOR UPDATE), puis relecture de la note courante.
            $professional = $intervention->getProfessional();
            $this->em->getConnection()->executeQuery(
                'SELECT 1 FROM professional_profile WHERE id = ? FOR UPDATE',
                [$professional->getId()->toRfc4122()],
            );
            $this->em->refresh($professional);
            $professional->recordRating($rating);

            return $review;
        });

        $this->audit->record('review.published', $client, 'Review', $review->getId()->toRfc4122(), [
            'intervention' => $intervention->getId()->toRfc4122(),
            'rating' => $rating,
        ]);

        return $review;
    }
}
