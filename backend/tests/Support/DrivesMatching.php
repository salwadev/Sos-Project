<?php

namespace App\Tests\Support;

use App\Domain\GeoPoint;
use App\Domain\Request\RequestTransition;
use App\Domain\Request\ServiceRequestStateMachine;
use App\Entity\Notification;
use App\Entity\RequestOffer;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Matching\DispatchOutcome;
use App\Matching\RequestDispatcher;
use App\Repository\RequestOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/** Mise en place d'une demande soumise, pilotage du moteur de diffusion et du worker Messenger dans les tests. */
trait DrivesMatching
{
    abstract protected function em(): EntityManagerInterface;

    /** @param array{float, float} $at [latitude, longitude], par défaut au centre de La Marsa */
    protected function submittedRequest(Service $service, ?ServiceArea $area, ?User $client = null, array $at = [36.88, 10.33]): ServiceRequest
    {
        $request = Factory::request($this->em(), $client, $service);
        $request->setLocation(GeoPoint::toEwkt(...$at));
        $request->setArea($area);
        static::getContainer()->get(ServiceRequestStateMachine::class)->apply($request, RequestTransition::Submit, $request->getClient());
        $this->em()->flush();

        return $request;
    }

    protected function dispatch(ServiceRequest $request): DispatchOutcome
    {
        return static::getContainer()->get(RequestDispatcher::class)->dispatch($request);
    }

    /**
     * Messages en attente sur le transport « async » (en mémoire en test, cf. config/packages/messenger.yaml).
     *
     * @return list<object>
     */
    protected function queuedAsyncMessages(): array
    {
        return array_map(static fn (Envelope $e) => $e->getMessage(), [...$this->asyncTransport()->get()]);
    }

    /** Traite les messages « async » en attente comme le ferait messenger:consume ; renvoie leur nombre. */
    protected function consumeAsyncMessages(): int
    {
        $transport = $this->asyncTransport();
        $bus = static::getContainer()->get(MessageBusInterface::class);
        $handled = 0;
        while ([] !== $envelopes = [...$transport->get()]) {
            foreach ($envelopes as $envelope) {
                $bus->dispatch($envelope->with(new ReceivedStamp('async')));
                $transport->ack($envelope);
                ++$handled;
            }
        }

        return $handled;
    }

    private function asyncTransport(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.async');
    }

    /** @return list<Notification> notifications du destinataire, de la plus ancienne à la plus récente */
    protected function notificationsOf(User $recipient): array
    {
        return $this->em()->getRepository(Notification::class)->findBy(['recipient' => $recipient], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    /** @return list<RequestOffer> */
    protected function offersOf(ServiceRequest $request): array
    {
        return static::getContainer()->get(RequestOfferRepository::class)->findForRequest($request);
    }

    /**
     * @return array<string, string> nom du professionnel => statut de son offre, par vague puis par nom
     */
    protected function solicitations(ServiceRequest $request, ?int $wave = null): array
    {
        $offers = array_filter($this->offersOf($request), static fn (RequestOffer $o) => null === $wave || $wave === $o->getWave());
        $result = [];
        foreach ($offers as $offer) {
            $result[$offer->getProfessional()->getDisplayName()] = $offer->getStatus()->value;
        }
        ksort($result);

        return $result;
    }

    /** Simule l'écoulement du délai de réponse des sollicitations en cours. */
    protected function elapseResponseDeadlines(ServiceRequest $request): void
    {
        $this->em()->getConnection()->executeStatement(
            "UPDATE request_offer SET respond_before = now() - interval '1 second' WHERE request_id = ? AND status = 'sent'",
            [$request->getId()->toRfc4122()],
        );
        $this->em()->clear();
    }

    /** @return list<string> transitions « DE→VERS » relues en base */
    protected function history(ServiceRequest $request): array
    {
        return $this->em()->getConnection()->fetchFirstColumn(
            "SELECT from_status || '→' || to_status FROM service_request_transition WHERE request_id = ? ORDER BY occurred_at, id",
            [$request->getId()->toRfc4122()],
        );
    }
}
