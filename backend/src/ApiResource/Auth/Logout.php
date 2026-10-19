<?php

namespace App\ApiResource\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Auth\LogoutProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/auth/logout — révoque le jeton de rafraîchissement ; le jeton d'accès expire seul (15 min). */
#[ApiResource(
    shortName: 'Logout',
    operations: [
        new Post(
            uriTemplate: '/auth/logout',
            status: 204,
            output: false,
            processor: LogoutProcessor::class,
            description: 'Termine la session sur cet appareil.',
        ),
    ],
)]
final class Logout
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public ?string $refreshToken = null;
}
