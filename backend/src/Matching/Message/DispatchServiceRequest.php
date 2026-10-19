<?php

namespace App\Matching\Message;

/**
 * Demande fraîchement soumise à diffuser (§5.8, §8.3) : envoyé par CreateServiceRequestProcessor sur le
 * transport « async », pour que la réponse 201 parte sans attendre la recherche de professionnels.
 * Si le message se perd, le passage périodique (MatchingTick) prend la demande en charge.
 */
final class DispatchServiceRequest
{
    public function __construct(public readonly string $requestId)
    {
    }
}
