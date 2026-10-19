<?php

namespace App\State\Request;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Request\CancelServiceRequestInput;
use App\Audit\AuditLogger;
use App\Domain\Clock;
use App\Domain\Request\RequestTransition;
use App\Domain\Request\ServiceRequestStateMachine;
use App\Entity\Intervention;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\InterventionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Annule une demande si son état le permet (§8.5) ; sinon 409 via InvalidRequestTransition.
 *
 * @implements ProcessorInterface<CancelServiceRequestInput, ServiceRequest>
 */
final class CancelServiceRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ServiceRequestStateMachine $stateMachine,
        private readonly AuditLogger $audit,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ServiceRequest
    {
        \assert($data instanceof CancelServiceRequestInput);
        $actor = $this->security->getUser();
        \assert($actor instanceof User);

        $request = $this->em->find(ServiceRequest::class, $uriVariables['id'] ?? null)
            ?? throw new NotFoundHttpException();

        $reason = null === $data->reason ? null : trim($data->reason);
        $this->stateMachine->apply($request, RequestTransition::Cancel, $actor, '' === $reason ? null : $reason);
        // Annulée après confirmation (ACCEPTED) : l'intervention, pas encore commencée, est annulée avec elle.
        $this->em->getRepository(Intervention::class)->findOneBy(['request' => $request])
            ?->setStatus(InterventionStatus::Cancelled, Clock::now());
        $this->em->flush();

        $this->audit->record('request.cancelled', $actor, 'ServiceRequest', $request->getId()->toRfc4122(), [
            'reference' => $request->getReference(),
            'byOwner' => $request->isOwnedBy($actor),
        ]);

        return $request;
    }
}
