<?php

namespace App\Domain\Request;

use App\Domain\Clock;
use App\Entity\ServiceRequest;
use App\Entity\ServiceRequestTransition;
use App\Entity\User;
use App\Enum\ServiceRequestStatus;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Seul point d'entrée pour changer l'état d'une demande (§4.5, §8.4).
 * Refuse toute transition invalide et trace chaque changement (acteur, motif, horodatage UTC).
 * L'appelant est responsable du flush.
 */
final class ServiceRequestStateMachine
{
    private const TERMINAL = [
        ServiceRequestStatus::Completed,
        ServiceRequestStatus::Cancelled,
        ServiceRequestStatus::Expired,
    ];

    public function __construct(
        private readonly WorkflowInterface $serviceRequestStateMachine,
    ) {
    }

    public function can(ServiceRequest $request, RequestTransition $transition): bool
    {
        return $this->serviceRequestStateMachine->can($request, $transition->value);
    }

    /** @throws InvalidRequestTransition */
    public function apply(ServiceRequest $request, RequestTransition $transition, ?User $actor, ?string $reason = null): void
    {
        if (!$this->can($request, $transition)) {
            throw new InvalidRequestTransition($request->getStatusEnum(), $transition);
        }

        $from = $request->getStatus();
        $now = Clock::now();
        $this->serviceRequestStateMachine->apply($request, $transition->value, ['actor' => $actor?->getUserIdentifier()]);

        new ServiceRequestTransition($request, $transition->value, $from, $request->getStatus(), $actor, $reason, $now);

        if (RequestTransition::Submit === $transition) {
            $request->markSubmitted($now);
        }
        if (RequestTransition::Cancel === $transition && null !== $actor) {
            $request->recordCancellation($actor, $reason);
        }
        if (\in_array($request->getStatusEnum(), self::TERMINAL, true)) {
            $request->markClosed($now);
        }
    }

    /** @return list<RequestTransition> */
    public function availableTransitions(ServiceRequest $request): array
    {
        return array_values(array_filter(
            RequestTransition::cases(),
            fn (RequestTransition $t) => $this->can($request, $t),
        ));
    }
}
