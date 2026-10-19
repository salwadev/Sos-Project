<?php

namespace App\Tests\Integration;

use App\Entity\Intervention;
use App\Entity\ProfessionalDocument;
use App\Entity\ProfessionalProfile;
use App\Entity\RequestOffer;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Enum\OfferStatus;
use App\Enum\ServiceRequestStatus;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Enum\VerificationStatus;
use App\Matching\DispatchOutcome;
use App\Matching\OfferResponder;
use App\Tests\Support\DrivesMatching;
use App\Tests\Support\Factory;
use App\Tests\Support\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Moteur de matching sur PostgreSQL/PostGIS réel — §5.8, §8.1–8.3, §16.2. */
final class MatchingTest extends KernelTestCase
{
    use DrivesMatching;
    use ResetsDatabase;

    private Service $plumbing;
    private ServiceArea $laMarsa;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase($this->em());
        $this->plumbing = Factory::service($this->em(), Factory::category($this->em(), 'home'), 'plumbing');
        $this->laMarsa = Factory::laMarsa($this->em());
        $this->now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function pro(string $name, array $base = [36.885, 10.33], ?Service $service = null, ?ServiceArea $area = null): ProfessionalProfile
    {
        return Factory::availableProfessional($this->em(), $service ?? $this->plumbing, $area ?? $this->laMarsa, $base, name: $name);
    }

    public function testOnlyEligibleProfessionalsReceiveAnOffer(): void
    {
        $this->pro('eligible');

        $this->pro('account suspended')->getUser()->setStatus(UserStatus::Suspended);
        $this->pro('verification suspended')->setVerificationStatus(VerificationStatus::Suspended, 'Plaintes répétées', $this->now);
        $this->pro('unverified')->setVerificationStatus(VerificationStatus::Pending, null, $this->now);
        $this->pro('out of zone', area: Factory::area($this->em(), 'carthage', [10.31, 36.84, 10.35, 36.86]));
        $this->pro('unavailable')->getAvailability()->goUnavailable();
        $this->pro('other trade', service: Factory::service($this->em(), Factory::category($this->em(), 'car'), 'battery'));

        $busy = $this->pro('busy elsewhere');
        $elsewhere = Factory::request($this->em(), service: $this->plumbing);
        $offer = new RequestOffer($elsewhere, $busy, 1, 20_000, $this->now, $this->now->modify('+1 minute'));
        $this->em()->persist($offer);
        $this->em()->persist(new Intervention($elsewhere, $offer));
        $this->em()->flush();

        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);
        self::assertSame(DispatchOutcome::WaveSent, $this->dispatch($request));

        self::assertSame(['eligible' => 'sent'], $this->solicitations($request), 'Suspendus, non vérifiés, hors zone, indisponibles ou occupés : jamais sollicités (§16.2).');
        self::assertSame(ServiceRequestStatus::Searching, $request->getStatusEnum());
    }

    public function testAQualifiedTradeRequiresAValidQualificationDocument(): void
    {
        $electricity = Factory::service($this->em(), Factory::category($this->em(), 'energy'), 'electricity');
        $electricity->setRequiresQualification(true);
        $reviewer = Factory::user($this->em(), UserRole::Admin);

        $this->pro('no document', service: $electricity);
        $documents = [
            'approved' => [DocumentStatus::Approved, null],
            'approved, valid until next year' => [DocumentStatus::Approved, $this->now->modify('+1 year')],
            'approved but expired' => [DocumentStatus::Approved, $this->now->modify('-1 day')],
            'pending review' => [DocumentStatus::Pending, null],
            'rejected' => [DocumentStatus::Rejected, null],
        ];
        foreach ($documents as $name => [$status, $expiresAt]) {
            $document = new ProfessionalDocument($this->pro($name, service: $electricity), DocumentType::Qualification, 'docs/'.md5($name), 'application/pdf', 1024);
            $document->setExpiresAt($expiresAt);
            if (DocumentStatus::Pending !== $status) {
                $document->review($status, $reviewer, $this->now);
            }
        }
        $identityOnly = $this->pro('identity only', service: $electricity);
        (new ProfessionalDocument($identityOnly, DocumentType::Identity, 'docs/id', 'image/jpeg', 2048))->review(DocumentStatus::Approved, $reviewer, $this->now);
        $this->em()->flush();

        $request = $this->submittedRequest($electricity, $this->laMarsa);
        $this->dispatch($request);

        self::assertSame(
            ['approved' => 'sent', 'approved, valid until next year' => 'sent'],
            $this->solicitations($request),
        );
    }

    public function testWavesFollowTheRankingThenTheRequestExpires(): void
    {
        // Distance d'abord (PostGIS), puis taux de réponse, puis note moyenne (§8.2).
        $this->pro('A nearest', [36.881, 10.33])->setResponseRate(0.1);
        $this->pro('B fast responder', [36.890, 10.33])->setResponseRate(0.9);
        $this->pro('C well rated', [36.890, 10.33])->setResponseRate(0.5);
        $this->pro('D poorly rated', [36.890, 10.33])->setResponseRate(0.5);
        $this->pro('E slow responder', [36.890, 10.33])->setResponseRate(0.2);
        $this->em()->flush();
        $this->em()->getConnection()->executeStatement("UPDATE professional_profile SET rating_average = 4.8 WHERE display_name = 'C well rated'");
        $this->em()->getConnection()->executeStatement("UPDATE professional_profile SET rating_average = 3.1 WHERE display_name = 'D poorly rated'");

        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);

        self::assertSame(DispatchOutcome::WaveSent, $this->dispatch($request));
        self::assertSame(['A nearest' => 'sent', 'B fast responder' => 'sent', 'C well rated' => 'sent'], $this->solicitations($request, 1), 'Vagues de 3 (§8.3).');
        foreach ($this->offersOf($request) as $offer) {
            self::assertSame(20_000, $offer->getTravelFeeMillimes(), 'Frais connus du professionnel.');
            self::assertGreaterThan($offer->getSentAt(), $offer->getRespondBefore(), 'Délai de réponse fixé.');
        }

        self::assertSame(DispatchOutcome::Waiting, $this->dispatch($request), 'Pas de nouvelle vague avant la fin du délai de réponse.');

        $this->elapseResponseDeadlines($request);
        $request = $this->em()->find($request::class, $request->getId());
        self::assertSame(DispatchOutcome::WaveSent, $this->dispatch($request));
        self::assertSame(['D poorly rated' => 'sent', 'E slow responder' => 'sent'], $this->solicitations($request, 2));
        self::assertSame(['A nearest' => 'expired', 'B fast responder' => 'expired', 'C well rated' => 'expired'], $this->solicitations($request, 1));

        $this->elapseResponseDeadlines($request);
        $request = $this->em()->find($request::class, $request->getId());
        self::assertSame(DispatchOutcome::Expired, $this->dispatch($request), 'Plus personne : la demande expire (§5.8).');
        self::assertSame(ServiceRequestStatus::Expired, $request->getStatusEnum());
        self::assertNotNull($request->getClosedAt());
        self::assertSame(['DRAFT→SUBMITTED', 'SUBMITTED→SEARCHING', 'SEARCHING→EXPIRED'], $this->history($request));
    }

    public function testARequestWithNobodyEligibleExpiresAtOnce(): void
    {
        $this->pro('far away', area: Factory::area($this->em(), 'carthage', [10.31, 36.84, 10.35, 36.86]));

        $inZone = $this->submittedRequest($this->plumbing, $this->laMarsa);
        $outsideAnyZone = $this->submittedRequest($this->plumbing, null, at: [36.80, 10.18]);

        self::assertSame(DispatchOutcome::Expired, $this->dispatch($inZone));
        self::assertSame(DispatchOutcome::Expired, $this->dispatch($outsideAnyZone));
        self::assertSame([], $this->offersOf($inZone));
        self::assertSame(['DRAFT→SUBMITTED', 'SUBMITTED→SEARCHING', 'SEARCHING→EXPIRED'], $this->history($outsideAnyZone));
    }

    public function testAnUnconfirmedReservationReopensTheSearch(): void
    {
        $first = $this->pro('first', [36.881, 10.33]);
        $this->pro('second', [36.890, 10.33]);
        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);
        $this->dispatch($request);

        $offer = array_values(array_filter($this->offersOf($request), static fn (RequestOffer $o) => $o->getProfessional() === $first))[0];
        static::getContainer()->get(OfferResponder::class)->reserve($offer, $first->getUser());
        self::assertSame(ServiceRequestStatus::Offered, $request->getStatusEnum());
        self::assertSame(DispatchOutcome::Reserved, $this->dispatch($request), 'Réservation en cours : on attend le client.');

        $this->em()->getConnection()->executeStatement(
            "UPDATE request_offer SET reserved_until = now() - interval '1 second' WHERE id = ?",
            [$offer->getId()->toRfc4122()],
        );
        $this->em()->clear();
        $request = $this->em()->find($request::class, $request->getId());

        self::assertSame(DispatchOutcome::Waiting, $this->dispatch($request), 'Le second professionnel peut encore répondre.');
        self::assertSame(ServiceRequestStatus::Searching, $request->getStatusEnum());
        self::assertSame(['first' => OfferStatus::Expired->value, 'second' => OfferStatus::Sent->value], $this->solicitations($request));
        self::assertSame('SEARCHING→OFFERED', $this->history($request)[2]);
        self::assertSame('OFFERED→SEARCHING', $this->history($request)[3]);
    }
}
