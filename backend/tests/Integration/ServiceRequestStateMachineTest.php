<?php

namespace App\Tests\Integration;

use App\Domain\Request\InvalidRequestTransition;
use App\Domain\Request\RequestTransition;
use App\Domain\Request\ServiceRequestStateMachine;
use App\Entity\ServiceRequest;
use App\Enum\ServiceRequestStatus;
use App\Tests\Support\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Machine à états réelle (config/packages/workflow.yaml), sans base de données — §8.4, §16.5. */
final class ServiceRequestStateMachineTest extends KernelTestCase
{
    private ServiceRequestStateMachine $machine;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->machine = static::getContainer()->get(ServiceRequestStateMachine::class);
    }

    /** @param list<RequestTransition> $transitions */
    private function walk(ServiceRequest $request, array $transitions): void
    {
        foreach ($transitions as $transition) {
            $this->machine->apply($request, $transition, $request->getClient());
        }
    }

    public function testNominalPathFromDraftToCompleted(): void
    {
        $request = Factory::request();
        self::assertSame(ServiceRequestStatus::Draft, $request->getStatusEnum());

        $this->walk($request, [
            RequestTransition::Submit,
            RequestTransition::StartSearch,
            RequestTransition::ReceiveOffer,
            RequestTransition::AcceptOffer,
            RequestTransition::StartWork,
            RequestTransition::Complete,
        ]);

        self::assertSame(ServiceRequestStatus::Completed, $request->getStatusEnum());
        self::assertNotNull($request->getSubmittedAt());
        self::assertNotNull($request->getClosedAt());

        $history = array_map(
            static fn ($t) => $t->getFromStatus().'→'.$t->getToStatus(),
            $request->getTransitions()->toArray(),
        );
        self::assertSame([
            'DRAFT→SUBMITTED',
            'SUBMITTED→SEARCHING',
            'SEARCHING→OFFERED',
            'OFFERED→ACCEPTED',
            'ACCEPTED→IN_PROGRESS',
            'IN_PROGRESS→COMPLETED',
        ], $history);
    }

    public function testARejectedOfferReopensTheSearch(): void
    {
        $request = Factory::request();
        $this->walk($request, [RequestTransition::Submit, RequestTransition::StartSearch, RequestTransition::ReceiveOffer, RequestTransition::ReopenSearch]);

        self::assertSame(ServiceRequestStatus::Searching, $request->getStatusEnum());
        self::assertNull($request->getClosedAt());
    }

    /** @return iterable<string, array{list<RequestTransition>, RequestTransition}> */
    public static function forbiddenTransitions(): iterable
    {
        $searching = [RequestTransition::Submit, RequestTransition::StartSearch];
        $offered = [...$searching, RequestTransition::ReceiveOffer];
        $inProgress = [...$offered, RequestTransition::AcceptOffer, RequestTransition::StartWork];

        yield 'terminer un brouillon' => [[], RequestTransition::Complete];
        yield 'accepter sans offre' => [$searching, RequestTransition::AcceptOffer];
        yield 'accepter une demande expirée (§16.5)' => [[...$offered, RequestTransition::Expire], RequestTransition::AcceptOffer];
        yield 'resoumettre une demande annulée' => [[RequestTransition::Submit, RequestTransition::Cancel], RequestTransition::Submit];
        yield 'annuler deux fois' => [[RequestTransition::Submit, RequestTransition::Cancel], RequestTransition::Cancel];
        yield 'annuler pendant les travaux' => [$inProgress, RequestTransition::Cancel];
        yield 'expirer pendant les travaux' => [$inProgress, RequestTransition::Expire];
        yield 'contester avant les travaux' => [$offered, RequestTransition::Dispute];
    }

    /** @param list<RequestTransition> $path */
    #[DataProvider('forbiddenTransitions')]
    public function testForbiddenTransitionsAreRejected(array $path, RequestTransition $forbidden): void
    {
        $request = Factory::request();
        $this->walk($request, $path);
        $before = $request->getStatus();
        $historySize = $request->getTransitions()->count();

        try {
            $this->machine->apply($request, $forbidden, $request->getClient());
            self::fail('La transition aurait dû être refusée.');
        } catch (InvalidRequestTransition $e) {
            self::assertSame($forbidden, $e->transition);
        }

        self::assertSame($before, $request->getStatus(), 'Le statut ne change pas.');
        self::assertSame($historySize, $request->getTransitions()->count(), 'Aucune trace n\'est ajoutée.');
    }

    public function testCancellationRecordsWhoAndWhy(): void
    {
        $request = Factory::request();
        $this->walk($request, [RequestTransition::Submit]);
        $this->machine->apply($request, RequestTransition::Cancel, $request->getClient(), 'Fuite réparée par un voisin');

        self::assertSame(ServiceRequestStatus::Cancelled, $request->getStatusEnum());
        self::assertSame('Fuite réparée par un voisin', $request->getCancelReason());
        self::assertSame($request->getClient(), $request->getCancelledBy());
        self::assertNotNull($request->getClosedAt());
    }

    public function testADisputeAfterCompletionCanBeResolved(): void
    {
        $request = Factory::request();
        $this->walk($request, [
            RequestTransition::Submit, RequestTransition::StartSearch, RequestTransition::ReceiveOffer,
            RequestTransition::AcceptOffer, RequestTransition::StartWork, RequestTransition::Complete,
            RequestTransition::Dispute,
        ]);
        self::assertSame(ServiceRequestStatus::Disputed, $request->getStatusEnum());

        $this->machine->apply($request, RequestTransition::ResolveDispute, null, 'Remboursement partiel');
        self::assertSame(ServiceRequestStatus::Completed, $request->getStatusEnum());
    }

    public function testAvailableTransitionsFromSubmitted(): void
    {
        $request = Factory::request();
        $this->walk($request, [RequestTransition::Submit]);

        self::assertEqualsCanonicalizing(
            [RequestTransition::StartSearch, RequestTransition::Cancel, RequestTransition::Expire],
            $this->machine->availableTransitions($request),
        );
    }
}
