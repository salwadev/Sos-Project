<?php

namespace App\Auth;

/** Réponse de /auth/verify-otp et /auth/refresh. */
final class TokenPair
{
    /** @param list<string> $roles */
    public function __construct(
        public readonly string $token,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
        public readonly string $userId,
        public readonly array $roles,
        public readonly bool $newAccount = false,
    ) {
    }
}
