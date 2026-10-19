<?php

namespace App\Command;

use App\Audit\AuditLogger;
use App\Domain\Clock;
use App\Domain\GeoPoint;
use App\Entity\Availability;
use App\Entity\ClientProfile;
use App\Entity\ProfessionalDocument;
use App\Entity\ProfessionalProfile;
use App\Entity\ProfessionalService;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Enum\VerificationStatus;
use App\Repository\UserRepository;
use App\Storage\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Uid\Uuid;

/**
 * Comptes de démonstration pour tester l'application à la main : un admin, un client et trois professionnels
 * vérifiés, disponibles, couvrant La Marsa et proposant la plomberie (l'un fait aussi l'électricité, avec sa
 * qualification approuvée). Charge d'abord le catalogue (app:seed-catalog).
 * Idempotent : relancer la commande remet ces comptes dans l'état décrit (vérifiés, disponibles, justificatifs
 * approuvés) sans doublon. Refusée en production.
 */
#[AsCommand(name: 'app:seed-demo', description: 'Crée les comptes de démonstration (admin, client, professionnels vérifiés)')]
final class SeedDemoCommand extends Command
{
    public const ADMIN_PHONE = '+21650000001';
    public const CLIENT_PHONE = '+21650000002';

    /** Durée de disponibilité donnée aux professionnels : le maximum autorisé (app.availability.max_minutes). */
    private const AVAILABILITY_MINUTES = 720;

    /**
     * téléphone => [nom affiché, statut juridique, [lat, lon] de départ, services[code => frais de déplacement]].
     * Les justificatifs exigés sont déduits : identité, registre pour une entreprise, qualification si un service l'exige.
     */
    public const PROFESSIONALS = [
        '+21650000011' => ['Sami B. (plombier)', ProfessionalProfile::LEGAL_INDIVIDUAL, [36.885, 10.330], ['plumbing' => 15_000]],
        '+21650000012' => ['Hela K. (plomberie, électricité)', ProfessionalProfile::LEGAL_INDIVIDUAL, [36.878, 10.322], ['plumbing' => 18_000, 'electricity' => 20_000]],
        '+21650000013' => ['Marsa Dépannage SARL', ProfessionalProfile::LEGAL_COMPANY, [36.892, 10.341], ['plumbing' => 25_000]],
    ];

    private const AREA_CODE = 'la_marsa';

    /** Contenu des justificatifs fictifs : un PDF minimal. */
    private const PLACEHOLDER_PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly DocumentStorage $storage,
        private readonly AuditLogger $audit,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('prod' === $this->environment) {
            $io->error('app:seed-demo crée des comptes aux numéros connus : interdit en production.');

            return Command::FAILURE;
        }

        $catalogInput = new ArrayInput([]);
        $catalogInput->setInteractive(false);
        $catalog = $this->getApplication()?->find('app:seed-catalog');
        if (null !== $catalog && Command::SUCCESS !== $catalog->run($catalogInput, $output)) {
            return Command::FAILURE;
        }

        $area = $this->em->getRepository(ServiceArea::class)->findOneBy(['code' => self::AREA_CODE]);
        if (null === $area) {
            $io->error('Zone « la_marsa » introuvable : lancer php bin/console app:seed-catalog.');

            return Command::FAILURE;
        }

        $now = Clock::now();
        $admin = $this->user(self::ADMIN_PHONE, UserRole::Admin);
        $client = $this->user(self::CLIENT_PHONE, UserRole::Client);
        $clientProfile = $client->getClientProfile() ?? new ClientProfile($client);
        $clientProfile->setFirstName($clientProfile->getFirstName() ?? 'Amira');
        $this->em->persist($clientProfile);

        $rows = [['Admin', 'ROLE_ADMIN', $admin->getPhone()], ['Client', 'ROLE_CLIENT', $client->getPhone()]];
        foreach (self::PROFESSIONALS as $phone => [$name, $legalStatus, $base, $services]) {
            $profile = $this->professional($phone, $name, $legalStatus, $base, $services, $area, $admin, $now);
            $rows[] = ['Professionnel', $profile->getDisplayName(), $phone];
        }
        $this->em->flush();

        $this->audit->record('demo.seeded', null, 'System', null, [
            'environment' => $this->environment,
            'professionals' => \count(self::PROFESSIONALS),
        ]);

        $io->success('Comptes de démonstration prêts.');
        $io->table(['Compte', 'Rôle / nom', 'Téléphone'], $rows);
        $io->text([
            \sprintf('Professionnels vérifiés et disponibles jusqu\'à %s UTC (relancer la commande pour prolonger).', $now->modify(\sprintf('+%d minutes', self::AVAILABILITY_MINUTES))->format('Y-m-d H:i')),
            'Connexion : POST /api/auth/request-otp {"phone": "<téléphone>"} puis POST /api/auth/verify-otp {"phone", "code"}.',
            \sprintf('En dev, le code à 6 chiffres est écrit dans %s (dernière ligne du numéro).', Path::join($this->projectDir, 'var', 'sms_outbox.log')),
            'Back-office (cd backoffice && npm run dev) : se connecter avec le numéro admin.',
        ]);

        return Command::SUCCESS;
    }

    private function user(string $phone, UserRole $role): User
    {
        $user = $this->users->findOneBy(['phone' => $phone]);
        if (null === $user) {
            $user = new User($phone);
            $this->em->persist($user);
        }
        $user->addRole($role);
        $user->setStatus(UserStatus::Active);

        return $user;
    }

    /**
     * @param array{float, float}  $base
     * @param array<string, int>   $services code => frais de déplacement en millimes
     */
    private function professional(string $phone, string $name, string $legalStatus, array $base, array $services, ServiceArea $area, User $admin, \DateTimeImmutable $now): ProfessionalProfile
    {
        $user = $this->user($phone, UserRole::Professional);
        $profile = $user->getProfessionalProfile();
        if (null === $profile) {
            $profile = new ProfessionalProfile($user, $name);
            $this->em->persist($profile);
        }
        $profile->setDisplayName($name);
        $profile->setLegalStatus($legalStatus);
        $profile->setBaseLocation(GeoPoint::toEwkt(...$base));
        $profile->addServiceArea($area);

        $needsQualification = false;
        foreach ($services as $code => $travelFee) {
            $service = $this->em->getRepository(Service::class)->findOneBy(['code' => $code])
                ?? throw new \RuntimeException(\sprintf('Service « %s » introuvable : lancer app:seed-catalog.', $code));
            $offering = $profile->findOffering($service) ?? new ProfessionalService($profile, $service, $travelFee);
            $offering->setTravelFeeMillimes($travelFee);
            $needsQualification = $needsQualification || $service->requiresQualification();
        }

        $required = [DocumentType::Identity];
        if (ProfessionalProfile::LEGAL_COMPANY === $legalStatus) {
            $required[] = DocumentType::CompanyRegistration;
        }
        if ($needsQualification) {
            $required[] = DocumentType::Qualification;
        }
        foreach ($required as $type) {
            if (!$profile->hasValidDocument($type, $now)) {
                $this->approvedDocument($profile, $type, $admin, $now);
            }
        }

        if (!$profile->isVerified()) {
            $profile->setVerificationStatus(VerificationStatus::Verified, 'Compte de démonstration (app:seed-demo).', $now);
        }
        $availability = $profile->getAvailability() ?? new Availability($profile);
        $availability->goAvailable($now, $now->modify(\sprintf('+%d minutes', self::AVAILABILITY_MINUTES)));

        return $profile;
    }

    private function approvedDocument(ProfessionalProfile $profile, DocumentType $type, User $admin, \DateTimeImmutable $now): void
    {
        $key = \sprintf('professionals/%s/%s', $profile->getId()->toRfc4122(), Uuid::v4()->toRfc4122());
        $source = tempnam(sys_get_temp_dir(), 'sos-demo-');
        if (false === $source) {
            throw new \RuntimeException('Fichier temporaire impossible à créer.');
        }
        try {
            file_put_contents($source, self::PLACEHOLDER_PDF);
            $this->storage->put($key, $source);
        } finally {
            @unlink($source);
        }

        $document = new ProfessionalDocument($profile, $type, $key, 'application/pdf', \strlen(self::PLACEHOLDER_PDF));
        $document->setExpiresAt($now->modify('+1 year')->setTime(0, 0));
        $document->review(DocumentStatus::Approved, $admin, $now);
        $this->em->persist($document);
    }
}
