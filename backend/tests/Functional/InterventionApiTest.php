<?php

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\Commission;
use App\Entity\Complaint;
use App\Entity\ProfessionalProfile;
use App\Entity\Quote;
use App\Entity\Review;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\ServiceRequest;
use App\Enum\CommissionStatus;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\DrivesMatching;
use App\Tests\Support\Factory;

/** Parcours après confirmation : étapes, devis versionnés, clôture, commission, contestation, avis — §5.5–5.7, §6.4, §6.6, §9, §16.1, §16.4. */
final class InterventionApiTest extends ApiTestCase
{
    use DrivesMatching;

    private const DIAGNOSTIC = [
        ['label' => 'Déplacement', 'amountMillimes' => 20_000],
        ['label' => 'Remplacement du siphon', 'amountMillimes' => 45_000],
    ];

    private Service $plumbing;
    private ServiceArea $laMarsa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plumbing = Factory::service($this->em(), Factory::category($this->em(), 'home'), 'plumbing');
        $this->laMarsa = Factory::laMarsa($this->em());
    }

    private function pro(string $name = 'Sami'): ProfessionalProfile
    {
        return Factory::availableProfessional($this->em(), $this->plumbing, $this->laMarsa, name: $name);
    }

    /**
     * Demande diffusée, acceptée par le professionnel puis confirmée par le client.
     *
     * @return array{string, string, string, ServiceRequest} [id de l'intervention, jeton client, jeton pro, demande]
     */
    private function confirmedIntervention(?ProfessionalProfile $pro = null): array
    {
        $pro ??= $this->pro();
        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);
        $request->setLandmark('En face de la pharmacie');
        $this->em()->flush();
        $this->dispatch($request);
        $offerId = $this->offersOf($request)[0]->getId()->toRfc4122();
        $clientToken = $this->tokenFor($request->getClient());
        $proToken = $this->tokenFor($pro->getUser());

        $this->api('POST', "/api/offers/$offerId/accept", [], $proToken);
        self::assertSame(200, $this->httpStatus());
        $confirmed = $this->api('POST', "/api/offers/$offerId/accept", [], $clientToken);
        self::assertSame(200, $this->httpStatus());

        return [$confirmed['interventionId'], $clientToken, $proToken, $request];
    }

    /** Fait avancer l'intervention jusqu'aux travaux, devis du diagnostic accepté. */
    private function startWork(string $id, string $clientToken, string $proToken): void
    {
        $quote = $this->api('POST', "/api/interventions/$id/quotes", ['lines' => self::DIAGNOSTIC], $proToken);
        self::assertSame(201, $this->httpStatus());
        $this->api('POST', '/api/quotes/'.$quote['pendingQuote']['id'].'/accept', null, $clientToken);
        self::assertSame(200, $this->httpStatus());
        $this->step($id, 'working', $proToken);
        self::assertSame(200, $this->httpStatus());
    }

    /** @return array<string, mixed>|null */
    private function step(string $id, string $status, string $token, ?int $etaMinutes = null): ?array
    {
        return $this->api('POST', "/api/interventions/$id/status", array_filter(
            ['status' => $status, 'estimatedArrivalMinutes' => $etaMinutes],
            static fn ($v) => null !== $v,
        ), $token);
    }

    private function commissionsCount(): int
    {
        return \count($this->em()->getRepository(Commission::class)->findAll());
    }

    public function testFullJourneyFromSubmissionToASingleCommission(): void
    {
        $sami = $this->pro();
        $clientToken = $this->tokenFor(Factory::client($this->em()));
        $samiToken = $this->tokenFor($sami->getUser());

        // Le client soumet ; le worker diffuse ; le professionnel est notifié et accepte ; le client confirme.
        $created = $this->api('POST', '/api/requests', [
            'serviceCode' => 'plumbing',
            'urgency' => 'asap',
            'description' => 'Une fuite sous l’évier de la cuisine.',
            'addressLine' => 'Rue des Orangers, La Marsa',
            'latitude' => 36.88,
            'longitude' => 10.33,
        ], $clientToken);
        self::assertSame(201, $this->httpStatus());
        $this->consumeAsyncMessages();
        self::assertSame(['offer.received'], array_map(static fn ($n) => $n->getType(), $this->notificationsOf($sami->getUser())));

        $offers = $this->api('GET', '/api/professional/requests', token: $samiToken);
        $offerId = $offers[0]['id'];
        $this->api('POST', "/api/offers/$offerId/accept", [], $samiToken);
        self::assertSame(200, $this->httpStatus());
        $id = $this->api('POST', "/api/offers/$offerId/accept", [], $clientToken)['interventionId'];
        self::assertSame(200, $this->httpStatus());

        // Exécution (§6.6).
        $onTheWay = $this->step($id, 'on_the_way', $samiToken, 25);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('on_the_way', $onTheWay['status']);
        self::assertEqualsWithDelta(time() + 25 * 60, (new \DateTimeImmutable($onTheWay['estimatedArrivalAt']))->getTimestamp(), 30);
        self::assertSame('on_site', $this->step($id, 'on_site', $samiToken)['status']);

        $quoted = $this->api('POST', "/api/interventions/$id/quotes", ['lines' => self::DIAGNOSTIC, 'explanation' => 'Siphon fissuré.'], $samiToken);
        self::assertSame(201, $this->httpStatus());
        self::assertSame(65_000, $quoted['pendingQuote']['totalMillimes']);
        self::assertNull($quoted['acceptedQuote']);
        $accepted = $this->api('POST', '/api/quotes/'.$quoted['pendingQuote']['id'].'/accept', null, $clientToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame(1, $accepted['acceptedQuote']['version']);
        self::assertNotNull($accepted['acceptedQuote']['decidedAt']);

        $working = $this->step($id, 'working', $samiToken);
        self::assertSame('working', $working['status']);
        self::assertSame('IN_PROGRESS', $working['request']['status']);
        $declared = $this->step($id, 'completion_declared', $samiToken);
        self::assertSame('completion_declared', $declared['status']);
        self::assertSame('IN_PROGRESS', $declared['request']['status'], 'La demande attend la confirmation du client.');
        self::assertSame(0, $this->commissionsCount());

        // Clôture : commission calculée une seule fois (§16.1).
        $done = $this->api('POST', "/api/interventions/$id/confirm-completion", null, $clientToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('completed', $done['status']);
        self::assertSame('COMPLETED', $done['request']['status']);
        self::assertNull($done['commission'], 'La commission ne regarde pas le client.');

        $this->api('POST', "/api/interventions/$id/confirm-completion", null, $clientToken);
        self::assertSame(409, $this->httpStatus());
        self::assertSame(1, $this->commissionsCount());

        $commission = $this->em()->getRepository(Commission::class)->findAll()[0];
        self::assertSame(65_000, $commission->getBaseMillimes());
        self::assertSame(1000, $commission->getRateBasisPoints());
        self::assertSame(6_500, $commission->getAmountMillimes());
        self::assertSame(CommissionStatus::Due, $commission->getStatus());
        $proView = $this->api('GET', "/api/interventions/$id", token: $samiToken);
        self::assertSame(6_500, $proView['commission']['amountMillimes']);

        self::assertSame([
            'DRAFT→SUBMITTED', 'SUBMITTED→SEARCHING', 'SEARCHING→OFFERED', 'OFFERED→ACCEPTED',
            'ACCEPTED→IN_PROGRESS', 'IN_PROGRESS→COMPLETED',
        ], $this->history($this->em()->find(ServiceRequest::class, $created['id'])));
        $audit = $this->em()->getRepository(AuditLog::class);
        foreach (['intervention.status_changed', 'quote.proposed', 'quote.accepted', 'intervention.completed'] as $action) {
            self::assertNotNull($audit->findOneBy(['action' => $action]), $action);
        }

        // Avis : un seul, sur l'intervention terminée.
        $reviewed = $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 5, 'comment' => 'Rapide et soigneux.'], $clientToken);
        self::assertSame(201, $this->httpStatus());
        self::assertSame(5, $reviewed['review']['rating']);
        self::assertSame(5.0, (float) $reviewed['professional']['ratingAverage']);
        self::assertSame(1, $reviewed['professional']['ratingCount']);

        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 1], $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Un seul avis par intervention.');
        self::assertCount(1, $this->em()->getRepository(Review::class)->findAll());
    }

    public function testTheExactAddressIsRevealedToTheAssignedProfessionalOnlyAfterConfirmation(): void
    {
        $sami = $this->pro();
        [$id, $clientToken, $samiToken] = $this->confirmedIntervention($sami);

        $view = $this->api('GET', "/api/interventions/$id", token: $samiToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('confirmed', $view['status']);
        self::assertSame('Rue des Orangers, La Marsa', $view['address']['addressLine']);
        self::assertSame('En face de la pharmacie', $view['address']['landmark']);
        self::assertEqualsWithDelta(36.88, $view['address']['latitude'], 1e-6);
        self::assertEqualsWithDelta(10.33, $view['address']['longitude'], 1e-6);
        self::assertSame(20_000, $view['travelFeeMillimes']);

        self::assertSame('Sami', $this->api('GET', "/api/interventions/$id", token: $clientToken)['professional']['displayName']);
        self::assertSame([$id], array_column($this->api('GET', '/api/interventions', token: $samiToken), 'id'));
        self::assertSame([$id], array_column($this->api('GET', '/api/interventions', token: $clientToken), 'id'));

        $outsider = $this->tokenFor(Factory::professional($this->em())->getUser());
        self::assertSame([], $this->api('GET', '/api/interventions', token: $outsider));
        $this->api('GET', "/api/interventions/$id", token: $outsider);
        self::assertSame(403, $this->httpStatus());
        $this->api('GET', "/api/interventions/$id", token: $this->tokenFor(Factory::client($this->em())));
        self::assertSame(403, $this->httpStatus());
        $this->api('GET', '/api/interventions/'.Factory::client()->getId(), token: $samiToken);
        self::assertSame(404, $this->httpStatus());

        // Une fois l'intervention close, l'adresse n'est plus exposée au professionnel.
        $this->startWork($id, $clientToken, $samiToken);
        $this->step($id, 'completion_declared', $samiToken);
        $this->api('POST', "/api/interventions/$id/confirm-completion", null, $clientToken);
        self::assertNull($this->api('GET', "/api/interventions/$id", token: $samiToken)['address']);
        self::assertNotNull($this->api('GET', "/api/interventions/$id", token: $clientToken)['address']);
    }

    public function testStatusesOnlyMoveForwardAndOnlyForTheAssignedProfessional(): void
    {
        [$id, $clientToken, $samiToken] = $this->confirmedIntervention();

        $this->step($id, 'on_the_way', $clientToken);
        self::assertSame(403, $this->httpStatus(), 'Le client ne déclare pas les étapes.');
        $this->step($id, 'on_the_way', $this->tokenFor(Factory::professional($this->em())->getUser()));
        self::assertSame(403, $this->httpStatus(), 'Seul le professionnel assigné.');
        $this->step($id, 'completed', $samiToken);
        self::assertSame(422, $this->httpStatus(), 'La clôture appartient au client.');
        $this->step($id, 'on_site', $samiToken, 10);
        self::assertSame(422, $this->httpStatus(), 'L\'heure estimée accompagne « en route ».');

        $this->step($id, 'completion_declared', $samiToken);
        self::assertSame(409, $this->httpStatus(), 'Pas de fin avant les travaux.');
        $this->step($id, 'working', $samiToken);
        self::assertSame(409, $this->httpStatus(), 'Pas de travaux sans devis accepté (§5.5).');

        self::assertSame('on_site', $this->step($id, 'on_site', $samiToken)['status'], '« en route » peut être omis.');
        $this->step($id, 'on_the_way', $samiToken);
        self::assertSame(409, $this->httpStatus(), 'Pas de retour en arrière.');
        $this->step($id, 'on_site', $samiToken);
        self::assertSame(409, $this->httpStatus());

        $this->api('POST', "/api/interventions/$id/confirm-completion", null, $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Le client ne confirme qu\'une fin déclarée.');
        self::assertSame(0, $this->commissionsCount());
    }

    public function testAPriceChangeIsOnlyAcceptedByAnExplicitClientAction(): void
    {
        [$id, $clientToken, $samiToken] = $this->confirmedIntervention();
        $this->startWork($id, $clientToken, $samiToken);
        $increase = [['label' => 'Remplacement de la canalisation', 'amountMillimes' => 120_000]];

        $this->api('POST', "/api/interventions/$id/quotes", ['lines' => $increase], $samiToken);
        self::assertSame(422, $this->httpStatus(), 'Une nouvelle version doit être expliquée (§6.6).');
        $this->api('POST', "/api/interventions/$id/quotes", ['lines' => [['label' => 'X', 'amountMillimes' => -1]], 'explanation' => 'Test'], $samiToken);
        self::assertSame(422, $this->httpStatus());
        $this->api('POST', "/api/interventions/$id/quotes", ['lines' => $increase, 'explanation' => 'Test'], $clientToken);
        self::assertSame(403, $this->httpStatus(), 'Le client ne propose pas de devis.');

        $v2 = $this->api('POST', "/api/interventions/$id/quotes", ['lines' => $increase, 'explanation' => 'Canalisation corrodée derrière le mur.'], $samiToken)['pendingQuote'];
        self::assertSame(2, $v2['version']);
        $this->api('POST', '/api/quotes/'.$v2['id'].'/accept', null, $samiToken);
        self::assertSame(403, $this->httpStatus(), 'Le professionnel ne peut pas accepter son propre devis.');
        $this->step($id, 'completion_declared', $samiToken);
        self::assertSame(409, $this->httpStatus(), 'Un devis attend le client : pas de clôture.');

        $rejected = $this->api('POST', '/api/quotes/'.$v2['id'].'/reject', null, $clientToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame(1, $rejected['acceptedQuote']['version'], 'Après refus, la version acceptée reste en vigueur.');
        self::assertNull($rejected['pendingQuote']);
        $this->api('POST', '/api/quotes/'.$v2['id'].'/accept', null, $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Un devis refusé ne se rattrape pas.');

        $v3 = $this->api('POST', "/api/interventions/$id/quotes", ['lines' => $increase, 'explanation' => 'Pièce indispensable.'], $samiToken)['pendingQuote'];
        $v4 = $this->api('POST', "/api/interventions/$id/quotes", ['lines' => [['label' => 'Canalisation (pièce)', 'amountMillimes' => 90_000]], 'explanation' => 'Pièce moins chère trouvée.'], $samiToken)['pendingQuote'];
        $this->api('POST', '/api/quotes/'.$v3['id'].'/accept', null, $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Une version remplacée ne peut plus être acceptée.');
        $final = $this->api('POST', '/api/quotes/'.$v4['id'].'/accept', null, $clientToken);
        self::assertSame(4, $final['acceptedQuote']['version']);
        self::assertSame(
            [[1, 'accepted'], [2, 'rejected'], [3, 'superseded'], [4, 'accepted']],
            array_map(static fn (array $q) => [$q['version'], $q['status']], $final['quotes']),
            'Toutes les versions sont conservées.',
        );

        $this->step($id, 'completion_declared', $samiToken);
        $this->api('POST', "/api/interventions/$id/confirm-completion", null, $clientToken);
        $commission = $this->em()->getRepository(Commission::class)->findAll()[0];
        self::assertSame(90_000, $commission->getBaseMillimes(), 'La commission porte sur la dernière version acceptée.');
        self::assertSame(9_000, $commission->getAmountMillimes());

        $this->api('POST', '/api/quotes/'.$v4['id'].'/reject', null, $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Plus de décision sur un devis après la clôture.');
        self::assertCount(4, $this->em()->getRepository(Quote::class)->findAll());
    }

    public function testTheClientCanDisputeWithATimestampedTrace(): void
    {
        [$id, $clientToken, $samiToken, $request] = $this->confirmedIntervention();
        $reason = 'La fuite a repris une heure après le départ du plombier.';

        $this->api('POST', "/api/interventions/$id/dispute", ['reason' => $reason], $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Avant les travaux, le client annule au lieu de contester.');

        $this->startWork($id, $clientToken, $samiToken);
        $this->api('POST', "/api/interventions/$id/dispute", ['reason' => 'court'], $clientToken);
        self::assertSame(422, $this->httpStatus());
        $this->api('POST', "/api/interventions/$id/dispute", ['reason' => $reason], $samiToken);
        self::assertSame(403, $this->httpStatus());

        $disputed = $this->api('POST', "/api/interventions/$id/dispute", ['reason' => $reason], $clientToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('disputed', $disputed['status']);
        self::assertSame('DISPUTED', $disputed['request']['status']);

        $trace = $this->em()->getConnection()->fetchAssociative(
            "SELECT reason, occurred_at FROM service_request_transition WHERE request_id = ? AND transition = 'dispute'",
            [$request->getId()->toRfc4122()],
        );
        self::assertSame($reason, $trace['reason']);
        self::assertNotNull($trace['occurred_at']);
        $complaint = $this->em()->getRepository(Complaint::class)->findOneBy(['request' => $request->getId()]);
        self::assertSame($reason, $complaint->getDescription());
        self::assertNotNull($this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'intervention.disputed']));

        $this->step($id, 'completion_declared', $samiToken);
        self::assertSame(409, $this->httpStatus(), 'Intervention gelée pendant la contestation.');
        $this->api('POST', "/api/interventions/$id/confirm-completion", null, $clientToken);
        self::assertSame(409, $this->httpStatus());
        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 1], $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Pas d\'avis sur une intervention contestée et non close.');
        self::assertSame(0, $this->commissionsCount());
    }

    public function testADisputeAfterCompletionKeepsTheCommissionForTheSupport(): void
    {
        [$id, $clientToken, $samiToken] = $this->confirmedIntervention();
        $this->startWork($id, $clientToken, $samiToken);
        $this->step($id, 'completion_declared', $samiToken);
        $this->api('POST', "/api/interventions/$id/confirm-completion", null, $clientToken);

        $this->api('POST', "/api/interventions/$id/dispute", ['reason' => 'Le joint fuit de nouveau ce matin.'], $clientToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame(1, $this->commissionsCount());
        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 2], $clientToken);
        self::assertSame(409, $this->httpStatus());
    }

    public function testReviewsAreReservedToTheClientOfACompletedIntervention(): void
    {
        [$id, $clientToken, $samiToken] = $this->confirmedIntervention();

        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 4], $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Intervention pas encore terminée.');

        $this->startWork($id, $clientToken, $samiToken);
        $this->step($id, 'completion_declared', $samiToken);
        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 4], $clientToken);
        self::assertSame(409, $this->httpStatus(), 'Fin déclarée mais pas confirmée.');
        $this->api('POST', "/api/interventions/$id/confirm-completion", null, $clientToken);

        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 4], $samiToken);
        self::assertSame(403, $this->httpStatus());
        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 6], $clientToken);
        self::assertSame(422, $this->httpStatus());
        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 4, 'comment' => str_repeat('a', 501)], $clientToken);
        self::assertSame(422, $this->httpStatus());

        $this->api('POST', "/api/interventions/$id/reviews", ['rating' => 4], $clientToken);
        self::assertSame(201, $this->httpStatus());
    }

    public function testCancellingAConfirmedRequestCancelsItsIntervention(): void
    {
        [$id, $clientToken, $samiToken, $request] = $this->confirmedIntervention();

        $this->api('POST', '/api/requests/'.$request->getId().'/cancel', ['reason' => 'Réparé par un voisin'], $clientToken);
        self::assertSame(200, $this->httpStatus());

        $view = $this->api('GET', "/api/interventions/$id", token: $samiToken);
        self::assertSame('cancelled', $view['status']);
        self::assertNull($view['address'], 'Plus d\'adresse pour une intervention annulée.');
        $this->step($id, 'on_the_way', $samiToken);
        self::assertSame(409, $this->httpStatus());
        $this->api('POST', "/api/interventions/$id/quotes", ['lines' => self::DIAGNOSTIC], $samiToken);
        self::assertSame(409, $this->httpStatus());
    }
}
