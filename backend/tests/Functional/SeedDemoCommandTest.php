<?php

namespace App\Tests\Functional;

use App\Audit\AuditLogger;
use App\Command\SeedDemoCommand;
use App\Entity\AuditLog;
use App\Entity\ProfessionalDocument;
use App\Entity\ProfessionalProfile;
use App\Entity\ProfessionalService;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\VerificationStatus;
use App\Matching\ProfessionalEligibility;
use App\Repository\UserRepository;
use App\Storage\DocumentStorage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\DrivesMatching;
use App\Tests\Support\RunsCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

/** app:seed-demo : comptes de démonstration utilisables tels quels, de la connexion OTP au matching. */
final class SeedDemoCommandTest extends ApiTestCase
{
    use DrivesMatching;
    use RunsCommands;

    protected function tearDown(): void
    {
        (new Filesystem())->remove(static::getContainer()->getParameter('app.documents.storage_dir'));
        parent::tearDown();
    }

    private function seed(): string
    {
        $tester = $this->runCommand('app:seed-demo');
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->em()->clear();

        return $tester->getDisplay();
    }

    private function user(string $phone): User
    {
        return static::getContainer()->get(UserRepository::class)->findOneBy(['phone' => $phone]);
    }

    private function service(string $code): Service
    {
        return $this->em()->getRepository(Service::class)->findOneBy(['code' => $code]);
    }

    private function rows(string $entity): int
    {
        return $this->em()->getRepository($entity)->count([]);
    }

    public function testCreatesAnAdminAClientAndVerifiedProfessionalsAndPrintsHowToLogIn(): void
    {
        $display = $this->seed();

        foreach ([SeedDemoCommand::ADMIN_PHONE, SeedDemoCommand::CLIENT_PHONE, ...array_keys(SeedDemoCommand::PROFESSIONALS)] as $phone) {
            self::assertStringContainsString($phone, $display);
        }
        self::assertStringContainsString('sms_outbox.log', $display);
        self::assertStringContainsString('/api/auth/request-otp', $display);

        self::assertContains('ROLE_ADMIN', $this->user(SeedDemoCommand::ADMIN_PHONE)->getRoles());
        $client = $this->user(SeedDemoCommand::CLIENT_PHONE);
        self::assertContains('ROLE_CLIENT', $client->getRoles());
        self::assertNotNull($client->getClientProfile());

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $laMarsa = $this->em()->getRepository(ServiceArea::class)->findOneBy(['code' => 'la_marsa']);
        $storage = static::getContainer()->get(DocumentStorage::class);
        foreach (SeedDemoCommand::PROFESSIONALS as $phone => [$name, $legalStatus]) {
            $profile = $this->user($phone)->getProfessionalProfile();
            self::assertNotNull($profile, $name);
            self::assertTrue($profile->isVerified(), $name);
            self::assertTrue($profile->getAvailability()?->isAvailableAt($now->modify('+11 hours')), $name);
            self::assertTrue($profile->coversArea($laMarsa), $name);
            self::assertNotNull($profile->findOffering($this->service('plumbing')), $name);
            self::assertTrue($profile->hasValidDocument(DocumentType::Identity, $now), $name);
            self::assertSame(ProfessionalProfile::LEGAL_COMPANY === $legalStatus, $profile->hasValidDocument(DocumentType::CompanyRegistration, $now), $name);
            foreach ($profile->getDocuments() as $document) {
                fclose($storage->read($document->getStorageKey()));
            }
        }

        $electrician = $this->user('+21650000012')->getProfessionalProfile();
        self::assertTrue($this->service('electricity')->requiresQualification());
        self::assertTrue($electrician->hasValidDocument(DocumentType::Qualification, $now), 'Qualification approuvée pour l\'électricité.');

        self::assertSame(1, $this->em()->getRepository(AuditLog::class)->count(['action' => 'demo.seeded']));
        self::assertSame([], static::getContainer()->get(AuditLogger::class)->verifyChain());
    }

    public function testTheDemoAdminLogsInByOtpAndSeesTheVerifiedProfessionals(): void
    {
        $this->seed();

        $tokens = $this->loginWithOtp(SeedDemoCommand::ADMIN_PHONE);
        self::assertContains('ROLE_ADMIN', $tokens['roles']);
        self::assertFalse($tokens['newAccount']);

        $professionals = $this->api('GET', '/api/admin/professionals?verificationStatus=verified', token: $tokens['token']);
        self::assertSame(200, $this->httpStatus());
        self::assertEqualsCanonicalizing(
            array_keys(SeedDemoCommand::PROFESSIONALS),
            array_column($professionals, 'phone'),
        );

        // En JSON-LD (back-office HydraAdmin), chaque membre a sa propre IRI d'admin, pas celle de /professional/profile.
        $collection = $this->api('GET', '/api/admin/professionals?verificationStatus=verified', token: $tokens['token'], headers: ['Accept' => 'application/ld+json']);
        self::assertSame(200, $this->httpStatus());
        self::assertSame(
            array_map(static fn (array $p) => '/api/admin/professionals/'.$p['id'], $collection['member']),
            array_column($collection['member'], '@id'),
        );
    }

    public function testADemoClientRequestReachesEveryDemoProfessionalQualifiedForIt(): void
    {
        $this->seed();
        $client = $this->user(SeedDemoCommand::CLIENT_PHONE);
        $laMarsa = $this->em()->getRepository(ServiceArea::class)->findOneBy(['code' => 'la_marsa']);
        $names = array_column(SeedDemoCommand::PROFESSIONALS, 0);
        sort($names);

        $plumbing = $this->submittedRequest($this->service('plumbing'), $laMarsa, $client);
        $this->dispatch($plumbing);
        self::assertSame(array_fill_keys($names, 'sent'), $this->solicitations($plumbing));

        // Seule la professionnelle qualifiée est sollicitée pour l'électricité (§8.1).
        $this->em()->clear();
        $client = $this->user(SeedDemoCommand::CLIENT_PHONE);
        $laMarsa = $this->em()->getRepository(ServiceArea::class)->findOneBy(['code' => 'la_marsa']);
        $electricity = $this->submittedRequest($this->service('electricity'), $laMarsa, $client);
        self::assertNull(static::getContainer()->get(ProfessionalEligibility::class)->exclusionReason(
            $this->user('+21650000012')->getProfessionalProfile(),
            $electricity,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ));
        $this->dispatch($electricity);
        self::assertSame(['Hela K. (plomberie, électricité)' => 'sent'], $this->solicitations($electricity));
    }

    public function testRunningItAgainRestoresTheDemoStateWithoutDuplicates(): void
    {
        $this->seed();
        $counts = fn () => [
            $this->rows(User::class),
            $this->rows(ProfessionalProfile::class),
            $this->rows(ProfessionalService::class),
            $this->rows(ProfessionalDocument::class),
            $this->rows(Service::class),
            $this->rows(ServiceArea::class),
        ];
        $before = $counts();

        $profile = $this->user('+21650000011')->getProfessionalProfile();
        $profile->setVerificationStatus(VerificationStatus::Suspended, 'Test', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $profile->getAvailability()->goUnavailable();
        $this->em()->flush();

        $this->seed();

        self::assertSame($before, $counts());
        $profile = $this->user('+21650000011')->getProfessionalProfile();
        self::assertTrue($profile->isVerified());
        self::assertTrue($profile->getAvailability()->isAvailableAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))));
    }

    public function testRefusesToRunInProduction(): void
    {
        $container = static::getContainer();
        $command = new SeedDemoCommand(
            $this->em(),
            $container->get(UserRepository::class),
            $container->get(DocumentStorage::class),
            $container->get(AuditLogger::class),
            'prod',
            $container->getParameter('kernel.project_dir'),
        );

        $tester = $this->runCommandObject($command);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('production', $tester->getDisplay());
        self::assertSame(0, $this->rows(User::class));
    }
}
