<?php

namespace App\ApiResource\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Auth\TokenPair;
use App\State\Auth\RefreshTokenProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/auth/refresh — rotation : l'ancien jeton de rafraîchissement est consommé. */
#[ApiResource(
    shortName: 'TokenRefresh',
    operations: [
        new Post(
            uriTemplate: '/auth/refresh',
            status: 200,
            output: TokenPair::class,
            processor: RefreshTokenProcessor::class,
            description: 'Renouvelle le jeton d\'accès.',
        ),
    ],
)]
final class TokenRefresh
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public ?string $refreshToken = null;
}
