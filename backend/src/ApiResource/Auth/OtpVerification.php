<?php

namespace App\ApiResource\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Auth\TokenPair;
use App\State\Auth\VerifyOtpProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** POST /api/auth/verify-otp — échange un code valide contre un jeton d'accès et un jeton de rafraîchissement. */
#[ApiResource(
    shortName: 'OtpVerification',
    operations: [
        new Post(
            uriTemplate: '/auth/verify-otp',
            status: 200,
            output: TokenPair::class,
            processor: VerifyOtpProcessor::class,
            description: 'Vérifie le code reçu par SMS ; crée le compte client au premier passage.',
        ),
    ],
)]
final class OtpVerification
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public ?string $phone = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code comporte 6 chiffres.')]
    public ?string $code = null;
}
