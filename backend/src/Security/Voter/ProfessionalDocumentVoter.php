<?php

namespace App\Security\Voter;

use App\Entity\ProfessionalDocument;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Lecture d'un justificatif (§16.6, §4.4) : le professionnel propriétaire et l'administration seulement.
 * Ni les autres professionnels, ni le support (données sensibles non visibles par défaut).
 *
 * @extends Voter<string, ProfessionalDocument>
 */
final class ProfessionalDocumentVoter extends Voter
{
    public const VIEW = 'PROFESSIONAL_DOCUMENT_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof ProfessionalDocument;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        return $user->hasRole(UserRole::Admin)
            || $subject->getProfessional()->getUser()->getId()->equals($user->getId());
    }
}
