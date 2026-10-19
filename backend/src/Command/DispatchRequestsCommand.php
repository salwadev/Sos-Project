<?php

namespace App\Command;

use App\Matching\ActiveRequestSweeper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fait avancer le matching à la main (§5.8, §8.3) : lance la recherche des demandes soumises, passe à la vague
 * suivante quand le délai de réponse est écoulé, relance la recherche quand une réservation n'est pas confirmée,
 * et fait expirer les demandes pour lesquelles plus personne n'est éligible.
 * En fonctionnement normal, ce passage est planifié (MatchingSchedule) et exécuté par messenger:consume ;
 * cette commande reste utile en dev, en dépannage, ou en cron/supervisor si le worker n'est pas déployé.
 */
#[AsCommand(name: 'app:matching:dispatch', description: 'Diffuse les demandes en attente aux professionnels éligibles, par vagues')]
final class DispatchRequestsCommand extends Command
{
    public function __construct(private readonly ActiveRequestSweeper $sweeper)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('watch', null, InputOption::VALUE_REQUIRED, 'Repasser toutes les N secondes au lieu de s\'arrêter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $interval = null === $input->getOption('watch') ? null : max(1, (int) $input->getOption('watch'));

        do {
            $counts = $this->sweeper->sweep();
            $io->writeln(\sprintf('[%s] %s', gmdate('H:i:s'), [] === $counts
                ? 'aucune demande active'
                : implode(', ', array_map(static fn (string $k, int $v) => "$k: $v", array_keys($counts), $counts))));
            if (null !== $interval) {
                sleep($interval);
            }
        } while (null !== $interval);

        return Command::SUCCESS;
    }
}
