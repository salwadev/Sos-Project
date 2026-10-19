<?php

namespace App\Domain\Request;

use App\Enum\ServiceRequestStatus;

/** Transition refusée par la machine à états : renvoyée en 409 Conflict (config/packages/api_platform.yaml). */
final class InvalidRequestTransition extends \DomainException
{
    public function __construct(
        public readonly ServiceRequestStatus $currentStatus,
        public readonly RequestTransition $transition,
    ) {
        parent::__construct(\sprintf(
            'Action « %s » impossible : la demande est à l\'état « %s ».',
            $transition->value,
            $currentStatus->value,
        ));
    }
}
