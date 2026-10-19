<?php

namespace App\State\Intervention;

use App\ApiResource\Intervention\InterventionView;
use App\Entity\Commission;
use App\Entity\Intervention;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/** Construit l'InterventionView renvoyée par chaque opération, pour l'utilisateur connecté. */
final class InterventionViews
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function currentUser(): User
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return $user;
    }

    public function for(Intervention $intervention): InterventionView
    {
        return InterventionView::for(
            $intervention,
            $this->currentUser(),
            $this->em->getRepository(Commission::class)->findOneBy(['intervention' => $intervention]),
            $this->em->getRepository(Review::class)->findOneBy(['intervention' => $intervention]),
        );
    }
}
