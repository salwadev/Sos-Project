<?php

namespace App\Command;

use App\Audit\AuditLogger;
use App\Auth\PhoneNumber;
use App\Entity\ClientProfile;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Donne un rôle à un compte depuis la console, pour amorcer le premier admin sans SQL (§4.3, §7.6).
 * Journalisé (« user.role_granted », canal console) ; sans effet si le compte a déjà le rôle.
 * Le rôle professionnel s'obtient par POST /api/professional/profile, qui crée aussi le profil à vérifier.
 */
#[AsCommand(name: 'app:user:grant-role', description: 'Ajoute un rôle (ROLE_ADMIN, ROLE_SUPPORT, ROLE_CLIENT) au compte de ce numéro')]
final class GrantRoleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('phone', InputArgument::REQUIRED, 'Numéro tunisien, ex. +21650000001 ou « 50 000 001 »')
            ->addArgument('role', InputArgument::REQUIRED, 'ROLE_ADMIN, ROLE_SUPPORT ou ROLE_CLIENT')
            ->addOption('create', null, InputOption::VALUE_NONE, 'Créer le compte s\'il n\'existe pas encore (sinon : se connecter une première fois par OTP)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $phone = PhoneNumber::normalizeTunisian((string) $input->getArgument('phone'));
        if (null === $phone) {
            $io->error('Numéro tunisien invalide (8 chiffres, éventuellement précédés de +216).');

            return Command::INVALID;
        }

        $role = UserRole::tryFrom(strtoupper((string) $input->getArgument('role')));
        if (null === $role) {
            $io->error(\sprintf('Rôle inconnu. Valeurs possibles : %s.', implode(', ', array_column(UserRole::cases(), 'value'))));

            return Command::INVALID;
        }
        if (UserRole::Professional === $role) {
            $io->error('Le rôle professionnel s\'obtient en créant le profil (POST /api/professional/profile), puis en le faisant vérifier.');

            return Command::INVALID;
        }

        $user = $this->users->findOneBy(['phone' => $phone]);
        $created = null === $user;
        if ($created) {
            if (!$input->getOption('create')) {
                $io->error(\sprintf('Aucun compte pour %s : se connecter une première fois par OTP, ou relancer avec --create.', $phone));

                return Command::FAILURE;
            }
            // Même compte que celui créé au premier passage OTP (VerifyOtpProcessor) : client avec son profil.
            $user = new User($phone, UserRole::Client);
            $this->em->persist(new ClientProfile($user));
            $this->em->persist($user);
        }

        if (!$created && $user->hasRole($role)) {
            $io->note(\sprintf('%s a déjà le rôle %s.', $phone, $role->value));

            return Command::SUCCESS;
        }

        $user->addRole($role);
        if (UserRole::Client === $role && null === $user->getClientProfile()) {
            $this->em->persist(new ClientProfile($user));
        }
        $this->em->flush();

        $this->audit->record('user.role_granted', null, 'User', $user->getUserIdentifier(), [
            'role' => $role->value,
            'channel' => 'console',
            'operator' => get_current_user(),
            'accountCreated' => $created,
        ]);

        $io->success(\sprintf('Rôle %s ajouté à %s%s. Il s\'applique dès la prochaine requête ; se reconnecter pour que l\'application le voie.', $role->value, $phone, $created ? ' (compte créé)' : ''));

        return Command::SUCCESS;
    }
}
