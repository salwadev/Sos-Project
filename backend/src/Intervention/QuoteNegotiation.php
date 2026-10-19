<?php

namespace App\Intervention;

use App\Audit\AuditLogger;
use App\Domain\Clock;
use App\Entity\Intervention;
use App\Entity\Quote;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\InterventionStatus;
use App\Enum\QuoteStatus;
use App\Matching\RequestLock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Devis versionnés (§5.5, §6.6, §9.2, §16.4) :
 * - le professionnel propose une version (explication obligatoire dès la deuxième) ; une version encore en attente
 *   est remplacée (« superseded »), jamais modifiée ;
 * - seul le client fait passer une version à « accepted » ou « rejected » ; aucun prix n'est accepté implicitement.
 *   Après un refus, la dernière version acceptée reste celle qui fait foi ; toutes les versions sont conservées.
 */
final class QuoteNegotiation
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestLock $lock,
        private readonly InterventionLifecycle $lifecycle,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param list<array{label: string, amountMillimes: int}> $lines
     *
     * @throws \InvalidArgumentException lignes ou explication invalides (422)
     */
    public function propose(Intervention $intervention, User $professional, array $lines, ?string $explanation): Quote
    {
        $quote = $this->lock->run($intervention->getRequest(), function (ServiceRequest $request) use ($intervention, $lines, $explanation): Quote {
            $this->lifecycle->reload($intervention);
            $this->assertNegotiable($request, $intervention);

            $version = 1;
            foreach ($intervention->getQuotes() as $previous) {
                $version = max($version, $previous->getVersion() + 1);
            }
            $quote = new Quote($intervention, $version, $lines, Clock::now(), $explanation);
            foreach ($intervention->getQuotes() as $previous) {
                if ($previous !== $quote && QuoteStatus::Proposed === $previous->getStatus()) {
                    $previous->supersede();
                }
            }
            $this->em->persist($quote);

            return $quote;
        });

        $this->audit->record('quote.proposed', $professional, 'Quote', $quote->getId()->toRfc4122(), [
            'intervention' => $intervention->getId()->toRfc4122(),
            'version' => $quote->getVersion(),
            'totalMillimes' => $quote->getTotalMillimes(),
            'previousAcceptedMillimes' => $intervention->getAcceptedQuote()?->getTotalMillimes(),
        ]);

        return $quote;
    }

    /** Action explicite du client : la version devient le prix qui fait foi, avec son horodatage (§9.2). */
    public function accept(Quote $quote, User $client): Quote
    {
        return $this->decide($quote, $client, true);
    }

    /** Refus explicite : la version est conservée comme refusée, la précédente version acceptée reste en vigueur. */
    public function reject(Quote $quote, User $client): Quote
    {
        return $this->decide($quote, $client, false);
    }

    private function decide(Quote $quote, User $client, bool $accept): Quote
    {
        $intervention = $quote->getIntervention();
        $this->lock->run($intervention->getRequest(), function (ServiceRequest $request) use ($quote, $intervention, $accept): void {
            $this->lifecycle->reload($intervention);
            $this->assertNegotiable($request, $intervention);
            if (QuoteStatus::Proposed !== $quote->getStatus()) {
                throw new InterventionConflict(match ($quote->getStatus()) {
                    QuoteStatus::Superseded => 'Ce devis a été remplacé par une version plus récente.',
                    default => 'Ce devis a déjà été traité.',
                });
            }

            $accept ? $quote->accept(Clock::now()) : $quote->reject(Clock::now());
        });

        $this->audit->record($accept ? 'quote.accepted' : 'quote.rejected', $client, 'Quote', $quote->getId()->toRfc4122(), [
            'intervention' => $intervention->getId()->toRfc4122(),
            'version' => $quote->getVersion(),
            'totalMillimes' => $quote->getTotalMillimes(),
        ]);

        return $quote;
    }

    /** Un devis se négocie jusqu'au début de la clôture, tant que la demande est en cours. */
    private function assertNegotiable(ServiceRequest $request, Intervention $intervention): void
    {
        InterventionLifecycle::assertInProgress($request);
        $rank = $intervention->getStatus()->executionRank();
        if (null === $rank || $rank > InterventionStatus::Working->executionRank()) {
            throw new InterventionConflict('La fin de l\'intervention a été déclarée : le devis ne peut plus changer.');
        }
    }
}
