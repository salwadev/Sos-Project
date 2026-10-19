<?php

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Auth\OtpRequest;
use App\Auth\OtpService;
use App\Auth\PhoneNumber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Limite les envois par numéro et par adresse IP (protection contre les abus OTP, §15.3).
 *
 * @implements ProcessorInterface<OtpRequest, null>
 */
final class RequestOtpProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly RateLimiterFactoryInterface $otpPhoneLimiter,
        private readonly RateLimiterFactoryInterface $otpIpLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        \assert($data instanceof OtpRequest);
        $phone = PhoneNumber::normalizeTunisian((string) $data->phone)
            ?? throw new UnprocessableEntityHttpException('Numéro de téléphone tunisien invalide.');

        $request = $context['request'] ?? null;
        $ip = $request instanceof Request ? ($request->getClientIp() ?? 'unknown') : 'unknown';

        foreach ([$this->otpIpLimiter->create($ip), $this->otpPhoneLimiter->create($phone)] as $limiter) {
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException(
                    max(1, $limit->getRetryAfter()->getTimestamp() - time()),
                    'Trop de demandes de code. Réessayez plus tard.',
                );
            }
        }

        $this->otp->issue($phone);

        return null;
    }
}
