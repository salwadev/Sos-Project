<?php

namespace App\Auth;

use App\Domain\Clock;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jeton d'accès JWT court + jeton de rafraîchissement opaque (§14.1).
 * Le jeton de rafraîchissement est à usage unique : sa réutilisation révoque toute la session (vol présumé).
 */
final class TokenIssuer
{
    public const REFRESH_TTL = '+30 days';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenRepository $refreshTokens,
        #[Autowire('%lexik_jwt_authentication.token_ttl%')]
        private readonly int $accessTokenTtl,
    ) {
    }

    public function issue(User $user, bool $newAccount = false): TokenPair
    {
        $refresh = bin2hex(random_bytes(32));
        $now = Clock::now();
        $this->em->persist(new RefreshToken($user, self::hash($refresh), $now, $now->modify(self::REFRESH_TTL)));
        $this->em->flush();

        return new TokenPair(
            $this->jwtManager->create($user),
            $refresh,
            $this->accessTokenTtl,
            $user->getUserIdentifier(),
            $user->getRoles(),
            $newAccount,
        );
    }

    /** @return User|null l'utilisateur si le jeton est valide (il est alors consommé) */
    public function consumeRefreshToken(string $refreshToken): ?User
    {
        $stored = $this->refreshTokens->findOneByHash(self::hash($refreshToken));
        if (null === $stored) {
            return null;
        }

        $now = Clock::now();
        if (null !== $stored->getRevokedAt()) {
            // Jeton déjà utilisé : on coupe toutes les sessions de ce compte.
            $this->refreshTokens->revokeAllFor($stored->getUser(), $now);

            return null;
        }
        if (!$stored->isValid($now) || !$stored->getUser()->isActive()) {
            return null;
        }

        $stored->revoke($now);
        $this->em->flush();

        return $stored->getUser();
    }

    public function revoke(string $refreshToken): void
    {
        $stored = $this->refreshTokens->findOneByHash(self::hash($refreshToken));
        if (null !== $stored) {
            $stored->revoke(Clock::now());
            $this->em->flush();
        }
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
