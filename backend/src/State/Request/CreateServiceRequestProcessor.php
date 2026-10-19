<?php

namespace App\State\Request;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Request\CreateServiceRequestInput;
use App\Domain\GeoPoint;
use App\Domain\Request\RequestTransition;
use App\Domain\Request\ServiceRequestStateMachine;
use App\Entity\Service;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\Urgency;
use App\Geo\ServiceAreaLocator;
use App\Matching\Message\DispatchServiceRequest;
use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Crée une demande et la soumet (DRAFT → SUBMITTED), puis demande sa diffusion en tâche de fond (§5.8) :
 * la réponse 201 renvoie donc SUBMITTED, la recherche de professionnels se poursuit dans le worker Messenger.
 * L'en-tête Idempotency-Key permet de rejouer l'appel sur réseau faible sans créer de doublon (§8.6, §15.5).
 *
 * @implements ProcessorInterface<CreateServiceRequestInput, ServiceRequest>
 */
final class CreateServiceRequestProcessor implements ProcessorInterface
{
    private const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ServiceRequestRepository $requests,
        private readonly ServiceRequestStateMachine $stateMachine,
        private readonly ServiceAreaLocator $areaLocator,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ServiceRequest
    {
        \assert($data instanceof CreateServiceRequestInput);
        $client = $this->security->getUser();
        \assert($client instanceof User);

        $idempotencyKey = $this->idempotencyKey($context['request'] ?? null);
        if (null !== $idempotencyKey) {
            $existing = $this->requests->findOneBy(['idempotencyKey' => $idempotencyKey]);
            if (null !== $existing) {
                if (!$existing->isOwnedBy($client)) {
                    throw new ConflictHttpException('Clé d\'idempotence déjà utilisée.');
                }

                return $existing;
            }
        }

        $service = $this->em->getRepository(Service::class)->findOneBy(['code' => $data->serviceCode, 'active' => true]);
        if (null === $service || !$service->getCategory()->isActive()) {
            throw new UnprocessableEntityHttpException('Service inconnu ou indisponible.');
        }

        $request = new ServiceRequest(
            $client,
            $service,
            Urgency::from((string) $data->urgency),
            trim((string) $data->description),
            trim((string) $data->addressLine),
        );
        $request->setLandmark($data->landmark);
        $request->setIdempotencyKey($idempotencyKey);

        if (null !== $data->latitude && null !== $data->longitude) {
            $point = GeoPoint::toEwkt($data->latitude, $data->longitude);
            $request->setLocation($point);
            $request->setArea($this->areaLocator->findActiveAreaCovering($point));
        }

        $this->em->persist($request);
        $this->stateMachine->apply($request, RequestTransition::Submit, $client);
        $this->em->flush();
        // Après le flush, pour que le worker trouve la demande en base. La demande est déjà enregistrée :
        // si l'envoi échoue, le passage périodique (MatchingTick) la diffusera quelques secondes plus tard.
        try {
            $this->bus->dispatch(new DispatchServiceRequest($request->getId()->toRfc4122()));
        } catch (MessengerException $e) {
            $this->logger->warning('Matching : diffusion de la demande {reference} différée au prochain passage.', [
                'reference' => $request->getReference(),
                'exception' => $e,
            ]);
        }

        return $request;
    }

    private function idempotencyKey(?Request $request): ?string
    {
        $key = $request?->headers->get(self::IDEMPOTENCY_HEADER);
        if (null === $key || '' === $key) {
            return null;
        }
        if (\strlen($key) > 80 || !preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            throw new BadRequestHttpException('En-tête Idempotency-Key invalide.');
        }

        return $key;
    }
}
