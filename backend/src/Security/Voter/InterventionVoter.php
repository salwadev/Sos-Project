<?php

namespace App\Security\Voter;

use App\Entity\Intervention;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Accès à une intervention (§4.5, §14.3, §14.4) :
 * - VIEW : client propriétaire de la demande, professionnel assigné, administration ; support sauf santé ;
 * - WORK : professionnel assigné seulement (étapes d'exécution, devis) ;
 * - CLIENT : client propriétaire seulement (décision sur les devis, fin des travaux, contestation, avis).
 * Ce qui est possible ensuite dépend de l'état (App\Intervention\*).
 *
 * @extends Voter<string, Intervention>
 */
final class InterventionVoter extends Voter
{
    public const VIEW = 'INTERVENTION_VIEW';
    public const WORK = 'INTERVENTION_WORK';
    public const CLIENT = 'INTERVENTION_CLIENT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::WORK, self::CLIENT], true) && $subject instanceof Intervention;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        return match ($attribute) {
            self::WORK => $subject->isAssignedTo($user),
            self::CLIENT => $subject->getRequest()->isOwnedBy($user),
            default => $subject->isAssignedTo($user)
                || $subject->getRequest()->isOwnedBy($user)
                || $user->hasRole(UserRole::Admin)
                || ($user->hasRole(UserRole::Support) && !$subject->getRequest()->isSensitive()),
        };
    }
}
