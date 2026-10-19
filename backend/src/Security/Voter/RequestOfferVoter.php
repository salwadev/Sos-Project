<?php

namespace App\Security\Voter;

use App\Entity\RequestOffer;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Réponse à une offre (§14.3, §14.4) : seuls le professionnel sollicité et le client propriétaire
 * de la demande peuvent agir ; ce qu'ils peuvent faire dépend ensuite de l'état (OfferResponder).
 *
 * @extends Voter<string, RequestOffer>
 */
final class RequestOfferVoter extends Voter
{
    public const RESPOND = 'OFFER_RESPOND';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::RESPOND === $attribute && $subject instanceof RequestOffer;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        return self::isSolicitedProfessional($subject, $user) || $subject->getRequest()->isOwnedBy($user);
    }

    public static function isSolicitedProfessional(RequestOffer $offer, User $user): bool
    {
        return $user->hasRole(UserRole::Professional)
            && $offer->getProfessional()->getUser()->getId()->equals($user->getId());
    }
}
