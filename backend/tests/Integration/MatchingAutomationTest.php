<?php

namespace App\Tests\Integration;

use App\Entity\Notification;
use App\Entity\ProfessionalProfile;
use App\Entity\RequestOffer;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\ServiceRequest;
use App\Enum\NotificationChannel;
use App\Enum\NotificationStatus;
use App\Enum\ServiceRequestStatus;
use App\Matching\MatchingSchedule;
use App\Matching\Message\DispatchServiceRequest;
use App\Matching\Message\MatchingTick;
use App\Matching\OfferResponder;
use App\Tests\Support\DrivesMatching;
use App\Tests\Support\Factory;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Uid\Uuid;

/** Passage planifié du matching et notifications des professionnels sollicités — §5.8, §6.4, §8.3, §12.5. */
final class MatchingAutomationTest extends KernelTestCase
{
    use DrivesMatching;
    use ResetsDatabase;

    private Service $plumbing;
    private ServiceArea $laMarsa;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase($this->em());
        $this->plumbing = Factory::service($this->em(), Factory::category($this->em(), 'home'), 'plumbing');
        $this->laMarsa = Factory::laMarsa($this->em());
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @param array{float, float} $base */
    private function pro(string $name, array $base = [36.885, 10.33], int $travelFeeMillimes = 20_000): ProfessionalProfile
    {
        return Factory::availableProfessional($this->em(), $this->plumbing, $this->laMarsa, $base, $travelFeeMillimes, $name);
    }

    private function tick(): void
    {
        static::getContainer()->get(MessageBusInterface::class)->dispatch(new MatchingTick());
    }

    private function reload(ServiceRequest $request): ServiceRequest
    {
        $this->em()->clear();

        return $this->em()->find(ServiceRequest::class, $request->getId());
    }

    public function testEachSolicitedProfessionalGetsAPendingPushWithoutTheExactAddress(): void
    {
        $sami = $this->pro('Sami', travelFeeMillimes: 15_000);
        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);
        $request->setLandmark('En face de la pharmacie');
        $this->em()->flush();

        $this->dispatch($request);

        [$notification] = $this->notificationsOf($sami->getUser());
        [$offer] = $this->offersOf($request);
        self::assertSame('offer.received', $notification->getType());
        self::assertSame(NotificationChannel::Push, $notification->getChannel());
        self::assertSame(NotificationStatus::Pending, $notification->getStatus(), 'Prête pour l\'envoi push à venir.');
        self::assertSame([
            'offerId' => $offer->getId()->toRfc4122(),
            'requestReference' => $request->getReference(),
            'categoryCode' => 'home',
            'serviceCode' => 'plumbing',
            'serviceName' => 'Plumbing',
            'areaName' => 'La_marsa',
            'approximateDistanceKm' => 1.0,
            'urgency' => 'asap',
            'travelFeeMillimes' => 15_000,
            'respondBefore' => $offer->getRespondBefore()->format(\DATE_ATOM),
        ], $notification->getPayload(), 'Catégorie, zone approximative, urgence, frais et délai uniquement (§6.4).');

        $serialized = json_encode($notification->getPayload(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        foreach ([$request->getAddressLine(), 'pharmacie', $request->getDescription(), '36.88', $request->getId()->toRfc4122()] as $secret) {
            self::assertStringNotContainsString($secret, $serialized, 'Ni adresse, ni repère, ni description, ni position exacte.');
        }
    }

    public function testOnlyTheProfessionalsOfTheNewWaveAreNotified(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $i => $name) {
            $this->pro($name, [36.881 + $i * 0.005, 10.33]);
        }
        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);

        $this->dispatch($request);
        self::assertSame(3, $this->em()->getRepository(Notification::class)->count([]), 'Une notification par offre de la vague 1.');

        $this->elapseResponseDeadlines($request);
        $this->dispatch($this->reload($request));

        $notified = $this->em()->getConnection()->fetchFirstColumn(
            "SELECT p.display_name FROM notification n JOIN professional_profile p ON p.user_id = n.recipient_id ORDER BY p.display_name",
        );
        self::assertSame(['A', 'B', 'C', 'D'], $notified, 'Les sollicités de la vague 1 ne sont pas notifiés une seconde fois.');
    }

    public function testThePeriodicTickStartsWavesThenExpiresTheRequest(): void
    {
        $this->pro('Sami');
        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);

        $this->tick();
        $request = $this->reload($request);
        self::assertSame(ServiceRequestStatus::Searching, $request->getStatusEnum(), 'Une demande soumise dont le message s\'est perdu est reprise.');
        self::assertSame(['Sami' => 'sent'], $this->solicitations($request));

        $this->tick();
        self::assertSame(['Sami' => 'sent'], $this->solicitations($this->reload($request)), 'Dans le délai de réponse : on attend.');

        $this->elapseResponseDeadlines($request);
        $this->tick();
        $request = $this->reload($request);
        self::assertSame(ServiceRequestStatus::Expired, $request->getStatusEnum());
        self::assertSame(['Sami' => 'expired'], $this->solicitations($request));
    }

    public function testThePeriodicTickReopensTheSearchWhenAReservationIsNotConfirmed(): void
    {
        $this->pro('Sami');
        $this->pro('Leila', [36.895, 10.33]);
        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);
        $this->dispatch($request);
        $offer = $this->offersOf($request)[0];
        static::getContainer()->get(OfferResponder::class)->reserve($offer, $offer->getProfessional()->getUser());
        $this->em()->getConnection()->executeStatement(
            "UPDATE request_offer SET reserved_until = now() - interval '1 second' WHERE id = ?",
            [$offer->getId()->toRfc4122()],
        );

        $this->tick();

        $request = $this->reload($request);
        self::assertSame(ServiceRequestStatus::Searching, $request->getStatusEnum());
        self::assertContains('OFFERED→SEARCHING', $this->history($request));
    }

    public function testTheScheduleEmitsATickEveryTenSeconds(): void
    {
        $messages = static::getContainer()->get(MatchingSchedule::class)->getSchedule()->getRecurringMessages();
        self::assertCount(1, $messages);
        $recurring = $messages[0];
        $context = new MessageContext('matching', $recurring->getId(), $recurring->getTrigger(), new \DateTimeImmutable());

        self::assertSame('every 10 seconds', (string) $recurring->getTrigger());
        self::assertEquals([new MatchingTick()], [...$recurring->getMessages($context)]);
    }

    public function testADispatchMessageForAVanishedRequestIsAcknowledgedWithoutError(): void
    {
        static::getContainer()->get(MessageBusInterface::class)->dispatch(new DispatchServiceRequest(Uuid::v7()->toRfc4122()));

        self::assertSame(1, $this->consumeAsyncMessages());
        self::assertSame(0, $this->em()->getRepository(RequestOffer::class)->count([]));
    }
}
