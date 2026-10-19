<?php

namespace App\State\Intervention;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Intervention\InterventionView;
use App\Entity\Quote;
use App\Intervention\QuoteNegotiation;

/**
 * POST /api/quotes/{id}/accept et /reject (§5.5, §16.4) : décision explicite du client propriétaire de la demande.
 * L'opération est désignée par son nom (quote_accept / quote_reject, cf. App\Entity\Quote).
 *
 * @implements ProcessorInterface<Quote, InterventionView>
 */
final class DecideQuoteProcessor implements ProcessorInterface
{
    public const ACCEPT = 'quote_accept';
    public const REJECT = 'quote_reject';

    public function __construct(
        private readonly QuoteNegotiation $quotes,
        private readonly InterventionViews $views,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InterventionView
    {
        \assert($data instanceof Quote);
        $client = $this->views->currentUser();

        if (self::ACCEPT === $operation->getName()) {
            $this->quotes->accept($data, $client);
        } else {
            $this->quotes->reject($data, $client);
        }

        return $this->views->for($data->getIntervention());
    }
}
