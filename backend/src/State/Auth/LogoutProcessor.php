<?php

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Auth\Logout;
use App\Auth\TokenIssuer;

/**
 * Révoque le jeton fourni. Réponse identique qu'il existe ou non.
 *
 * @implements ProcessorInterface<Logout, null>
 */
final class LogoutProcessor implements ProcessorInterface
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        \assert($data instanceof Logout);
        $this->tokens->revoke((string) $data->refreshToken);

        return null;
    }
}
