<?php

namespace App\Auth;

use App\Domain\Clock;
use App\Entity\OtpChallenge;
use App\Repository\OtpChallengeRepository;
use App\Sms\SmsSender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Émission et vérification des codes OTP (§5.1, §15.3).
 * Le code n'est jamais stocké ni journalisé en clair : seul un HMAC-SHA256 lié au numéro est conservé.
 */
final class OtpService
{
    public const TTL_SECONDS = 300;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OtpChallengeRepository $challenges,
        private readonly SmsSender $sms,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function issue(string $phoneE164): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', \STR_PAD_LEFT);
        $now = Clock::now();

        $this->em->persist(new OtpChallenge($phoneE164, $this->hash($phoneE164, $code), $now, $now->modify('+'.self::TTL_SECONDS.' seconds')));
        $this->em->flush();

        $this->sms->send($phoneE164, \sprintf(
            'Urgence Tunisie : votre code est %s. Il expire dans %d minutes. Ne le communiquez à personne.',
            $code,
            intdiv(self::TTL_SECONDS, 60),
        ));
    }

    /**
     * Vérifie le dernier code émis pour ce numéro. Un échec consomme un essai ; un succès consomme le code.
     */
    public function verify(string $phoneE164, string $code): bool
    {
        $challenge = $this->challenges->findLatestForPhone($phoneE164);
        $now = Clock::now();

        if (null === $challenge || !$challenge->isUsable($now)) {
            return false;
        }

        if (!hash_equals($challenge->getCodeHash(), $this->hash($phoneE164, $code))) {
            $challenge->registerFailedAttempt();
            $this->em->flush();

            return false;
        }

        $challenge->consume($now);
        $this->em->flush();

        return true;
    }

    private function hash(string $phoneE164, string $code): string
    {
        return hash_hmac('sha256', $phoneE164.'|'.$code, $this->secret);
    }
}
