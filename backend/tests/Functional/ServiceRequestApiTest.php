<?php

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\ServiceRequest;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Factory;

/** §5.3, §8.4–8.6, §16.5, §16.6 : création, lecture, annulation et autorisations sur les demandes. */
final class ServiceRequestApiTest extends ApiTestCase
{
    /** @var array<string, mixed> */
    private const PAYLOAD = [
        'serviceCode' => 'plumbing',
        'urgency' => 'asap',
        'description' => 'Une fuite sous l’évier de la cuisine.',
        'addressLine' => 'Rue des Orangers, La Marsa',
        'landmark' => 'En face de la pharmacie',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $em = $this->em();
        Factory::service($em, Factory::category($em, 'home'), 'plumbing');
        Factory::service($em, Factory::category($em, 'health', sensitive: true), 'on_call_dentist');
        Factory::laMarsa($em);
    }

    /** @return array{0: \App\Entity\User, 1: string} */
    private function client(): array
    {
        $user = Factory::client($this->em());

        return [$user, $this->tokenFor($user)];
    }

    /** @return array<string, mixed> */
    private function createRequest(string $token, array $overrides = [], array $headers = []): array
    {
        return $this->api('POST', '/api/requests', [...self::PAYLOAD, ...$overrides], $token, $headers);
    }

    public function testAClientCreatesASubmittedRequest(): void
    {
        [, $token] = $this->client();

        $request = $this->createRequest($token);

        self::assertSame(201, $this->httpStatus());
        self::assertSame('SUBMITTED', $request['status']);
        self::assertMatchesRegularExpression('/^UT-[2-9A-HJ-NP-Z]{6}$/', $request['reference']);
        self::assertSame('En face de la pharmacie', $request['landmark']);
        self::assertArrayHasKey('submittedAt', $request);
        self::assertStringEndsWith('+00:00', $request['submittedAt'], 'Horodatage en UTC (§8.6).');
    }

    public function testTheServiceAreaIsFoundFromCoordinates(): void
    {
        [, $token] = $this->client();

        $inside = $this->createRequest($token, ['latitude' => 36.88, 'longitude' => 10.33]);
        self::assertStringStartsWith('/api/service-areas/', $inside['area']);

        $outside = $this->createRequest($token, ['latitude' => 36.80, 'longitude' => 10.18]);
        self::assertArrayNotHasKey('area', $outside, 'Hors zone active : aucune zone rattachée.');
    }

    public function testTheIdempotencyKeyPreventsDuplicates(): void
    {
        [, $token] = $this->client();

        $first = $this->createRequest($token, headers: ['Idempotency-Key' => 'mobile-7f3a']);
        $retry = $this->createRequest($token, headers: ['Idempotency-Key' => 'mobile-7f3a']);

        self::assertSame($first['id'], $retry['id']);
        self::assertCount(1, $this->em()->getRepository(ServiceRequest::class)->findAll());
    }

    public function testInvalidPayloadsAreRejected(): void
    {
        [, $token] = $this->client();

        $this->createRequest($token, ['description' => '']);
        self::assertSame(422, $this->httpStatus());

        $this->createRequest($token, ['latitude' => 36.88]);
        self::assertSame(422, $this->httpStatus(), 'Latitude sans longitude.');

        $this->createRequest($token, ['serviceCode' => 'teleportation']);
        self::assertSame(422, $this->httpStatus());

        $this->createRequest($token, ['urgency' => 'yesterday']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testABodyThatIsNotValidUtf8IsABadRequest(): void
    {
        [, $token] = $this->client();
        // Apostrophe Windows-1252 (0x92) envoyée par un terminal qui n'est pas en UTF-8.
        $body = str_replace('’', "\x92", json_encode(self::PAYLOAD, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

        foreach (['application/json', 'application/ld+json'] as $contentType) {
            $this->client->request('POST', '/api/requests', server: [
                'CONTENT_TYPE' => $contentType,
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ], content: $body);

            $problem = json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            self::assertSame(400, $this->httpStatus(), $contentType);
            self::assertStringContainsString('UTF-8', $problem['detail'], $contentType);
        }
        self::assertCount(0, $this->em()->getRepository(ServiceRequest::class)->findAll());
    }

    public function testOnlyClientsCanCreateRequests(): void
    {
        $pro = Factory::user($this->em(), UserRole::Professional);

        $this->createRequest($this->tokenFor($pro));
        self::assertSame(403, $this->httpStatus());

        $this->api('POST', '/api/requests', self::PAYLOAD);
        self::assertSame(401, $this->httpStatus());
    }

    public function testAClientCannotSeeSomeoneElsesRequest(): void
    {
        [, $ownerToken] = $this->client();
        [, $otherToken] = $this->client();
        $request = $this->createRequest($ownerToken);

        $this->api('GET', '/api/requests/'.$request['id'], token: $ownerToken);
        self::assertSame(200, $this->httpStatus());

        $this->api('GET', '/api/requests/'.$request['id'], token: $otherToken);
        self::assertSame(403, $this->httpStatus());

        $list = $this->api('GET', '/api/requests', token: $otherToken);
        self::assertSame([], $list, 'La liste ne contient que ses propres demandes.');
    }

    public function testSupportDoesNotSeeHealthRequestsByDefault(): void
    {
        [, $token] = $this->client();
        $plumbing = $this->createRequest($token);
        $health = $this->createRequest($token, ['serviceCode' => 'on_call_dentist', 'description' => 'Rage de dents']);
        $supportToken = $this->tokenFor(Factory::user($this->em(), UserRole::Support));

        $this->api('GET', '/api/requests/'.$plumbing['id'], token: $supportToken);
        self::assertSame(200, $this->httpStatus());

        $this->api('GET', '/api/requests/'.$health['id'], token: $supportToken);
        self::assertSame(403, $this->httpStatus());

        $list = $this->api('GET', '/api/requests', token: $supportToken);
        self::assertSame([$plumbing['id']], array_column($list, 'id'));
    }

    public function testCancellationIsTracedAndOnlyPossibleOnce(): void
    {
        [$user, $token] = $this->client();
        $request = $this->createRequest($token);

        $cancelled = $this->api('POST', '/api/requests/'.$request['id'].'/cancel', ['reason' => 'Fuite réparée'], $token);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('CANCELLED', $cancelled['status']);
        self::assertSame('Fuite réparée', $cancelled['cancelReason']);
        self::assertArrayHasKey('closedAt', $cancelled);

        $error = $this->api('POST', '/api/requests/'.$request['id'].'/cancel', ['reason' => 'Encore'], $token);
        self::assertSame(409, $this->httpStatus(), 'Transition invalide rejetée (§8.4).');
        self::assertStringContainsString('CANCELLED', $error['detail']);

        $entity = $this->em()->getRepository(ServiceRequest::class)->find($request['id']);
        self::assertSame(
            ['DRAFT→SUBMITTED', 'SUBMITTED→CANCELLED'],
            array_map(static fn ($t) => $t->getFromStatus().'→'.$t->getToStatus(), $entity->getTransitions()->toArray()),
        );
        self::assertSame($user->getUserIdentifier(), $entity->getCancelledBy()?->getUserIdentifier());
        self::assertNotNull($this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'request.cancelled']));
    }

    public function testAnotherClientCannotCancel(): void
    {
        [, $ownerToken] = $this->client();
        [, $otherToken] = $this->client();
        $request = $this->createRequest($ownerToken);

        $this->api('POST', '/api/requests/'.$request['id'].'/cancel', [], $otherToken);
        self::assertSame(403, $this->httpStatus());

        $unchanged = $this->api('GET', '/api/requests/'.$request['id'], token: $ownerToken);
        self::assertSame('SUBMITTED', $unchanged['status']);
    }

    public function testAnAdminCanCancelAnyRequest(): void
    {
        [, $token] = $this->client();
        $request = $this->createRequest($token);
        $adminToken = $this->tokenFor(Factory::user($this->em(), UserRole::Admin));

        $cancelled = $this->api('POST', '/api/requests/'.$request['id'].'/cancel', ['reason' => 'Doublon'], $adminToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('CANCELLED', $cancelled['status']);
    }
}
