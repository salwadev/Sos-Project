<?php

namespace App\Security\Voter;

use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Accès à une demande (§4.4, §4.5, §16.6) :
 * - le client propriétaire la voit et peut l'annuler ;
 * - le support la voit, sauf les demandes santé (données sensibles masquées par défaut) ;
 * - l'administration voit et peut annuler.
 * L'état métier (annulable ou non) est vérifié ensuite par la machine à états.
 *
 * @extends Voter<string, ServiceRequest>
 */
final class ServiceRequestVoter extends Voter
{
    public const VIEW = 'REQUEST_VIEW';
    public const CANCEL = 'REQUEST_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::CANCEL], true) && $subject instanceof ServiceRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        if ($user->hasRole(UserRole::Admin) || $subject->isOwnedBy($user)) {
            return true;
        }

        return self::VIEW === $attribute
            && $user->hasRole(UserRole::Support)
            && !$subject->isSensitive();
    }
}
