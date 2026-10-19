<?php

namespace App\Matching;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Paramètres de diffusion (config/services.yaml, §5.8, §8.3). */
final class MatchingSettings
{
    public function __construct(
        #[Autowire('%app.matching.wave_size%')]
        public readonly int $waveSize,
        #[Autowire('%app.matching.offer_response_seconds%')]
        public readonly int $offerResponseSeconds,
        #[Autowire('%app.matching.reservation_seconds%')]
        public readonly int $reservationSeconds,
    ) {
        if ($waveSize < 1 || $offerResponseSeconds < 1 || $reservationSeconds < 1) {
            throw new \InvalidArgumentException('Paramètres de matching invalides.');
        }
    }

    public function responseDeadline(\DateTimeImmutable $from): \DateTimeImmutable
    {
        return $from->modify(\sprintf('+%d seconds', $this->offerResponseSeconds));
    }

    public function reservationEnd(\DateTimeImmutable $from): \DateTimeImmutable
    {
        return $from->modify(\sprintf('+%d seconds', $this->reservationSeconds));
    }
}
