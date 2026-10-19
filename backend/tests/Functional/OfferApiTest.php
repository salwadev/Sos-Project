<?php

namespace App\Tests\Functional;

use App\Domain\Request\RequestTransition;
use App\Domain\Request\ServiceRequestStateMachine;
use App\Entity\AuditLog;
use App\Entity\Intervention;
use App\Entity\ProfessionalProfile;
use App\Entity\RequestOffer;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\ServiceRequest;
use App\Enum\InterventionStatus;
use App\Enum\UserRole;
use App\Enum\VerificationStatus;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\DrivesMatching;
use App\Tests\Support\Factory;

/** Endpoints professionnels et réponses aux offres — §5.5, §6.3–6.5, §14.3–14.4, §16.3, §16.5. */
final class OfferApiTest extends ApiTestCase
{
    use DrivesMatching;

    private Service $plumbing;
    private ServiceArea $laMarsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plumbing = Factory::service($this->em(), Factory::category($this->em(), 'home'), 'plumbing');
        $this->laMarsa = Factory::laMarsa($this->em());
    }

    /** @param array{float, float} $base */
    private function pro(string $name, array $base = [36.885, 10.33], int $travelFeeMillimes = 20_000): ProfessionalProfile
    {
        return Factory::availableProfessional($this->em(), $this->plumbing, $this->laMarsa, $base, $travelFeeMillimes, $name);
    }

    /** Demande soumise puis diffusée : renvoie [demande, jeton du client]. */
    private function dispatchedRequest(): array
    {
        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);
        $request->setLandmark('En face de la pharmacie');
        $this->em()->flush();
        $this->dispatch($request);

        return [$request, $this->tokenFor($request->getClient())];
    }

    private function offerFor(ServiceRequest $request, ProfessionalProfile $professional): string
    {
        foreach ($this->offersOf($request) as $offer) {
            if ($offer->getProfessional()->getId()->equals($professional->getId())) {
                return $offer->getId()->toRfc4122();
            }
        }
        self::fail('Aucune offre pour ce professionnel.');
    }

    private function reload(ServiceRequest $request): ServiceRequest
    {
        $this->em()->clear();

        return $this->em()->find(ServiceRequest::class, $request->getId());
    }

    public function testAProfessionalTogglesItsAvailability(): void
    {
        $pro = $this->pro('Sami');
        $token = $this->tokenFor($pro->getUser());
        $patch = ['Content-Type' => 'application/merge-patch+json'];

        $off = $this->api('PATCH', '/api/professional/availability', ['available' => false], $token, $patch);
        self::assertSame(200, $this->httpStatus());
        self::assertFalse($off['available']);
        self::assertArrayNotHasKey('availableUntil', $off);

        $on = $this->api('PATCH', '/api/professional/availability', ['available' => true, 'durationMinutes' => 60, 'latitude' => 36.88, 'longitude' => 10.32], $token, $patch);
        self::assertSame(200, $this->httpStatus());
        self::assertTrue($on['available']);
        $until = new \DateTimeImmutable($on['availableUntil']);
        self::assertEqualsWithDelta(time() + 3600, $until->getTimestamp(), 30, 'La disponibilité expire (§6.3).');

        self::assertTrue($this->api('GET', '/api/professional/availability', token: $token)['available']);

        $this->api('PATCH', '/api/professional/availability', ['available' => true, 'durationMinutes' => 24 * 60], $token, $patch);
        self::assertSame(422, $this->httpStatus(), 'Au-delà du maximum, il faut reconfirmer.');

        $this->api('PATCH', '/api/professional/availability', ['durationMinutes' => 30], $token, $patch);
        self::assertSame(422, $this->httpStatus(), '« available » est obligatoire.');

        $this->api('PATCH', '/api/professional/availability', ['available' => true], $this->tokenFor(Factory::client($this->em())), $patch);
        self::assertSame(403, $this->httpStatus());
    }

    public function testAProfessionalSeesItsOpenOffersWithoutTheExactAddress(): void
    {
        $sami = $this->pro('Sami', [36.885, 10.33]);
        $outsider = Factory::availableProfessional($this->em(), $this->plumbing, Factory::area($this->em(), 'carthage', [10.31, 36.84, 10.35, 36.86]));
        [$request] = $this->dispatchedRequest();

        $this->client->request('GET', '/api/professional/requests', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor($sami->getUser()),
        ]);
        self::assertSame(200, $this->httpStatus());
        $raw = (string) $this->client->getResponse()->getContent();
        $offers = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);

        self::assertCount(1, $offers);
        self::assertSame('sent', $offers[0]['status']);
        self::assertSame(20_000, $offers[0]['travelFeeMillimes']);
        self::assertSame($request->getReference(), $offers[0]['request']['reference']);
        self::assertSame('plumbing', $offers[0]['request']['serviceCode']);
        self::assertSame('asap', $offers[0]['request']['urgency']);
        self::assertSame('La_marsa', $offers[0]['request']['areaName']);
        self::assertSame(1.0, (float) $offers[0]['request']['approximateDistanceKm'], '≈ 560 m, arrondi au demi-kilomètre supérieur.');
        self::assertArrayHasKey('respondBefore', $offers[0]);
        foreach (['Rue des Orangers', 'pharmacie', 'POINT', '36.88', 'addressLine', 'landmark', 'location'] as $secret) {
            self::assertStringNotContainsString($secret, $raw, 'Adresse et position exactes non révélées (§6.4).');
        }

        self::assertSame([], $this->api('GET', '/api/professional/requests', token: $this->tokenFor($outsider->getUser())));

        $this->api('GET', '/api/professional/requests', token: $this->tokenFor($request->getClient()));
        self::assertSame(403, $this->httpStatus());
    }

    public function testNominalFlowFromProfessionalAcceptanceToIntervention(): void
    {
        $sami = $this->pro('Sami', travelFeeMillimes: 25_000);
        [$request, $clientToken] = $this->dispatchedRequest();
        $offerId = $this->offerFor($request, $sami);
        $samiToken = $this->tokenFor($sami->getUser());

        $this->api('POST', "/api/offers/$offerId/accept", ['travelFeeMillimes' => 30_000], $samiToken);
        self::assertSame(422, $this->httpStatus(), 'Les frais annoncés ne peuvent pas être augmentés.');

        $reserved = $this->api('POST', "/api/offers/$offerId/accept", ['travelFeeMillimes' => 22_000], $samiToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('reserved', $reserved['status']);
        self::assertSame(22_000, $reserved['travelFeeMillimes']);
        self::assertSame('OFFERED', $reserved['request']['status']);
        self::assertNotNull($reserved['reservedUntil']);

        $visible = $this->api('GET', '/api/requests/'.$request->getId().'/offers', token: $clientToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame([$offerId], array_column($visible, 'id'));
        self::assertSame('Sami', $visible[0]['professional']['displayName']);
        self::assertSame(22_000, $visible[0]['travelFeeMillimes']);

        $this->api('GET', '/api/requests/'.$request->getId().'/offers', token: $this->tokenFor(Factory::client($this->em())));
        self::assertSame(403, $this->httpStatus());

        $confirmed = $this->api('POST', "/api/offers/$offerId/accept", [], $clientToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('confirmed', $confirmed['status']);
        self::assertSame('ACCEPTED', $confirmed['request']['status']);
        self::assertNotNull($confirmed['interventionId']);

        $intervention = $this->em()->find(Intervention::class, $confirmed['interventionId']);
        self::assertSame(InterventionStatus::Confirmed, $intervention->getStatus());
        self::assertTrue($intervention->getProfessional()->getId()->equals($sami->getId()));
        self::assertSame(
            ['DRAFT→SUBMITTED', 'SUBMITTED→SEARCHING', 'SEARCHING→OFFERED', 'OFFERED→ACCEPTED'],
            $this->history($request),
        );
        $audit = $this->em()->getRepository(AuditLog::class);
        self::assertNotNull($audit->findOneBy(['action' => 'offer.reserved']));
        self::assertNotNull($audit->findOneBy(['action' => 'offer.confirmed']));

        $this->api('POST', "/api/offers/$offerId/accept", [], $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Pas de double confirmation.');
    }

    public function testTheSecondProfessionalIsToldTheRequestIsTaken(): void
    {
        $first = $this->pro('First');
        $second = $this->pro('Second');
        [$request] = $this->dispatchedRequest();

        $this->api('POST', '/api/offers/'.$this->offerFor($request, $first).'/accept', [], $this->tokenFor($first->getUser()));
        self::assertSame(200, $this->httpStatus());

        $error = $this->api('POST', '/api/offers/'.$this->offerFor($request, $second).'/accept', [], $this->tokenFor($second->getUser()));
        self::assertSame(409, $this->httpStatus());
        self::assertSame('Demande déjà réservée par un autre professionnel.', $error['detail'], 'État explicite pour le perdant (§16.3).');
    }

    public function testAnExpiredRequestCannotBeAccepted(): void
    {
        $sami = $this->pro('Sami');
        [$request, $clientToken] = $this->dispatchedRequest();
        $offerId = $this->offerFor($request, $sami);

        static::getContainer()->get(ServiceRequestStateMachine::class)->apply($request, RequestTransition::Expire, null, 'Test');
        $this->em()->flush();

        $error = $this->api('POST', "/api/offers/$offerId/accept", [], $this->tokenFor($sami->getUser()));
        self::assertSame(409, $this->httpStatus(), 'Une demande expirée ne peut pas être acceptée (§16.5).');
        self::assertStringContainsString('expiré', $error['detail']);

        $this->api('POST', "/api/offers/$offerId/accept", [], $clientToken);
        self::assertSame(409, $this->httpStatus());

        self::assertSame('EXPIRED', $this->reload($request)->getStatus());
        self::assertSame('sent', $this->em()->find(RequestOffer::class, $offerId)->getStatus()->value);
    }

    public function testDeadlinesAreEnforced(): void
    {
        $sami = $this->pro('Sami');
        $karim = $this->pro('Karim');
        [$request, $clientToken] = $this->dispatchedRequest();

        $this->elapseResponseDeadlines($request);
        $error = $this->api('POST', '/api/offers/'.$this->offerFor($request, $sami).'/accept', [], $this->tokenFor($sami->getUser()));
        self::assertSame(409, $this->httpStatus());
        self::assertStringContainsString('délai de réponse', $error['detail']);

        // Réservation non confirmée à temps par le client.
        $this->em()->getConnection()->executeStatement("UPDATE request_offer SET respond_before = now() + interval '1 minute'");
        $karimOffer = $this->offerFor($request, $karim);
        $this->api('POST', "/api/offers/$karimOffer/accept", [], $this->tokenFor($karim->getUser()));
        self::assertSame(200, $this->httpStatus());
        $this->em()->getConnection()->executeStatement("UPDATE request_offer SET reserved_until = now() - interval '1 second' WHERE id = ?", [$karimOffer]);

        $error = $this->api('POST', "/api/offers/$karimOffer/accept", [], $clientToken);
        self::assertSame(409, $this->httpStatus());
        self::assertStringContainsString('réservation', $error['detail']);
        self::assertSame(0, $this->em()->getRepository(Intervention::class)->count([]));
    }

    public function testAProfessionalNoLongerEligibleCannotAccept(): void
    {
        $sami = $this->pro('Sami');
        [$request] = $this->dispatchedRequest();
        $sami->setVerificationStatus(VerificationStatus::Suspended, 'Contrôle en cours', new \DateTimeImmutable());
        $this->em()->flush();

        $error = $this->api('POST', '/api/offers/'.$this->offerFor($request, $sami).'/accept', [], $this->tokenFor($sami->getUser()));
        self::assertSame(409, $this->httpStatus(), 'Éligibilité revérifiée à l\'acceptation (§16.2).');
        self::assertStringContainsString('profil non vérifié', $error['detail']);
    }

    public function testDecliningMovesTheSearchForward(): void
    {
        $sami = $this->pro('Sami');
        [$request] = $this->dispatchedRequest();

        $declined = $this->api('POST', '/api/offers/'.$this->offerFor($request, $sami).'/decline', token: $this->tokenFor($sami->getUser()));
        self::assertSame(200, $this->httpStatus());
        self::assertSame('declined', $declined['status']);
        self::assertSame('EXPIRED', $this->reload($request)->getStatus(), 'Seul professionnel éligible : la demande expire (§5.8).');

        $this->api('POST', '/api/offers/'.$this->offerFor($request, $sami).'/decline', token: $this->tokenFor($sami->getUser()));
        self::assertSame(409, $this->httpStatus());
    }

    public function testTheClientCanRefuseTheProposedProfessional(): void
    {
        $sami = $this->pro('Sami', [36.881, 10.33]);
        $karim = $this->pro('Karim', [36.890, 10.33]);
        [$request, $clientToken] = $this->dispatchedRequest();
        $samiOffer = $this->offerFor($request, $sami);
        $this->api('POST', "/api/offers/$samiOffer/accept", [], $this->tokenFor($sami->getUser()));

        $rejected = $this->api('POST', "/api/offers/$samiOffer/decline", token: $clientToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('rejected', $rejected['status']);
        self::assertSame('SEARCHING', $rejected['request']['status'], 'La recherche reprend (reopen_search).');

        $this->api('POST', '/api/offers/'.$this->offerFor($request, $karim).'/accept', [], $this->tokenFor($karim->getUser()));
        self::assertSame(200, $this->httpStatus(), 'Un autre professionnel peut maintenant réserver.');
        self::assertNotNull($this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'offer.rejected']));
    }

    public function testOnlyTheSolicitedProfessionalOrTheOwnerMayRespond(): void
    {
        $sami = $this->pro('Sami');
        [$request] = $this->dispatchedRequest();
        $offerId = $this->offerFor($request, $sami);

        foreach ([
            Factory::user($this->em(), UserRole::Professional),
            Factory::client($this->em()),
            Factory::user($this->em(), UserRole::Support),
        ] as $stranger) {
            $this->api('POST', "/api/offers/$offerId/accept", [], $this->tokenFor($stranger));
            self::assertSame(403, $this->httpStatus());
            $this->api('POST', "/api/offers/$offerId/decline", token: $this->tokenFor($stranger));
            self::assertSame(403, $this->httpStatus());
        }

        $this->api('POST', '/api/offers/0190a4b2-0000-7000-8000-000000000000/accept', [], $this->tokenFor($sami->getUser()));
        self::assertSame(404, $this->httpStatus());
    }
}
