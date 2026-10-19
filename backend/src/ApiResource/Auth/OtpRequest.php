<?php

namespace App\ApiResource\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Auth\RequestOtpProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/auth/request-otp — envoie un code par SMS.
 * Réponse 202 identique que le numéro soit inscrit ou non (pas d'énumération des comptes, §5.1).
 */
#[ApiResource(
    shortName: 'OtpRequest',
    operations: [
        new Post(
            uriTemplate: '/auth/request-otp',
            status: 202,
            output: false,
            processor: RequestOtpProcessor::class,
            description: 'Envoie un code de connexion à 6 chiffres par SMS.',
        ),
    ],
)]
final class OtpRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public ?string $phone = null;
}
