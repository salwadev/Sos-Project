<?php

namespace App\Matching\Message;

use App\Matching\ActiveRequestSweeper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MatchingTickHandler
{
    public function __construct(
        private readonly ActiveRequestSweeper $sweeper,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(MatchingTick $tick): void
    {
        $counts = $this->sweeper->sweep();
        if ([] !== $counts) {
            $this->logger->debug('Matching : passage périodique {counts}.', ['counts' => $counts]);
        }
    }
}
