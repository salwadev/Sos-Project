<?php

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Auth\TokenRefresh;
use App\Auth\TokenIssuer;
use App\Auth\TokenPair;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @implements ProcessorInterface<TokenRefresh, TokenPair> */
final class RefreshTokenProcessor implements ProcessorInterface
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenPair
    {
        \assert($data instanceof TokenRefresh);
        $user = $this->tokens->consumeRefreshToken((string) $data->refreshToken)
            ?? throw new HttpException(401, 'Session expirée. Reconnectez-vous.');

        return $this->tokens->issue($user);
    }
}
