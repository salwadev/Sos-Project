<?php

namespace App\Tests\Functional;

use App\Entity\ServiceRequest;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Matching\Message\DispatchServiceRequest;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\DrivesMatching;
use App\Tests\Support\Factory;

/** Diffusion déclenchée par la soumission d'une demande, hors de la requête HTTP — §5.8, §8.3, §14.2. */
final class AutomaticDispatchTest extends ApiTestCase
{
    use DrivesMatching;

    /** @var array<string, mixed> */
    private const PAYLOAD = [
        'serviceCode' => 'plumbing',
        'urgency' => 'asap',
        'description' => 'Une fuite sous l’évier de la cuisine.',
        'addressLine' => 'Rue des Orangers, La Marsa',
        'latitude' => 36.88,
        'longitude' => 10.33,
    ];

    private Service $plumbing;
    private ServiceArea $laMarsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plumbing = Factory::service($this->em(), Factory::category($this->em(), 'home'), 'plumbing');
        $this->laMarsa = Factory::laMarsa($this->em());
    }

    public function testSubmittingARequestQueuesItsDispatchWithoutWaitingForIt(): void
    {
        $pro = Factory::availableProfessional($this->em(), $this->plumbing, $this->laMarsa, name: 'Sami');
        $token = $this->tokenFor(Factory::client($this->em()));

        $created = $this->api('POST', '/api/requests', self::PAYLOAD, $token);

        self::assertSame(201, $this->httpStatus());
        self::assertSame('SUBMITTED', $created['status'], 'La réponse n\'attend pas la recherche de professionnels.');
        self::assertEquals([new DispatchServiceRequest($created['id'])], $this->queuedAsyncMessages());

        self::assertSame(1, $this->consumeAsyncMessages());

        $request = $this->em()->find(ServiceRequest::class, $created['id']);
        self::assertSame('SEARCHING', $request->getStatus());
        self::assertSame(['DRAFT→SUBMITTED', 'SUBMITTED→SEARCHING'], $this->history($request));
        self::assertSame(['Sami' => 'sent'], $this->solicitations($request));

        $offers = $this->api('GET', '/api/professional/requests', token: $this->tokenFor($pro->getUser()));
        self::assertSame(200, $this->httpStatus());
        self::assertSame([$created['reference']], array_column(array_column($offers, 'request'), 'reference'));
    }

    public function testWithNobodyEligibleTheQueuedDispatchExpiresTheRequest(): void
    {
        $token = $this->tokenFor(Factory::client($this->em()));
        $created = $this->api('POST', '/api/requests', self::PAYLOAD, $token);
        self::assertSame('SUBMITTED', $created['status']);

        $this->consumeAsyncMessages();

        $request = $this->api('GET', '/api/requests/'.$created['id'], token: $token);
        self::assertSame('EXPIRED', $request['status'], 'Jamais présentée comme prise en charge (§5.8).');
    }

    public function testAnIdempotentReplayDoesNotQueueASecondDispatch(): void
    {
        $token = $this->tokenFor(Factory::client($this->em()));
        $headers = ['Idempotency-Key' => 'replay-1'];

        $first = $this->api('POST', '/api/requests', self::PAYLOAD, $token, $headers);
        self::assertCount(1, $this->queuedAsyncMessages());

        $replay = $this->api('POST', '/api/requests', self::PAYLOAD, $token, $headers);
        self::assertSame($first['id'], $replay['id']);
        self::assertSame([], $this->queuedAsyncMessages(), 'Le rejeu renvoie la demande existante sans la rediffuser.');
    }
}
