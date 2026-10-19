<?php

namespace App\Matching;

use App\Matching\Message\MatchingTick;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Planification du passage périodique (§5.8, §8.3), consommée par le transport « scheduler_matching » :
 *   php bin/console messenger:consume async scheduler_matching
 * Un seul worker doit consommer ce transport (sans symfony/lock, chaque consommateur émettrait ses propres
 * passages ; ce serait sans danger grâce à RequestLock, mais inutile).
 */
#[AsSchedule('matching')]
final class MatchingSchedule implements ScheduleProviderInterface
{
    public function __construct(
        #[Autowire('%app.matching.tick_seconds%')]
        private readonly int $tickSeconds,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(RecurringMessage::every(\sprintf('%d seconds', max(1, $this->tickSeconds)), new MatchingTick()));
    }
}
