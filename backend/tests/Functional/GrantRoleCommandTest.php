<?php

namespace App\Tests\Functional;

use App\Audit\AuditLogger;
use App\Entity\AuditLog;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Factory;
use App\Tests\Support\RunsCommands;
use Symfony\Component\Console\Command\Command;

/** app:user:grant-role : amorcer un admin sans SQL, de façon journalisée (§4.3, §7.6). */
final class GrantRoleCommandTest extends ApiTestCase
{
    use RunsCommands;

    private function user(string $phone): ?User
    {
        $this->em()->clear();

        return static::getContainer()->get(UserRepository::class)->findOneBy(['phone' => $phone]);
    }

    /** @return list<AuditLog> */
    private function grants(): array
    {
        return $this->em()->getRepository(AuditLog::class)->findBy(['action' => 'user.role_granted']);
    }

    public function testAGrantedAdminReachesTheAdminApiAndTheGrantIsAudited(): void
    {
        $user = Factory::client($this->em(), '+21650123456');
        $token = $this->tokenFor($user);
        $this->api('GET', '/api/admin/professionals', token: $token);
        self::assertSame(403, $this->httpStatus());

        $tester = $this->runCommand('app:user:grant-role', ['phone' => '50 123 456', 'role' => 'role_admin']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(['ROLE_CLIENT', 'ROLE_ADMIN', 'ROLE_USER'], $this->user('+21650123456')->getRoles());

        // Les rôles sont relus en base à chaque requête : le jeton déjà émis suffit.
        $this->api('GET', '/api/admin/professionals', token: $token);
        self::assertSame(200, $this->httpStatus());

        [$entry] = $this->grants();
        self::assertNull($entry->getActorId());
        self::assertSame('User', $entry->getSubjectType());
        self::assertSame($user->getUserIdentifier(), $entry->getSubjectId());
        self::assertSame('ROLE_ADMIN', $entry->getContext()['role']);
        self::assertSame('console', $entry->getContext()['channel']);
        self::assertFalse($entry->getContext()['accountCreated']);
        self::assertSame([], static::getContainer()->get(AuditLogger::class)->verifyChain());
    }

    public function testGrantingARoleTheAccountAlreadyHasChangesNothing(): void
    {
        $phone = Factory::user($this->em(), UserRole::Support)->getPhone();

        $tester = $this->runCommand('app:user:grant-role', ['phone' => $phone, 'role' => 'ROLE_SUPPORT']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('déjà', $tester->getDisplay());
        self::assertSame([], $this->grants());
    }

    public function testAnUnknownNumberNeedsTheCreateOption(): void
    {
        $tester = $this->runCommand('app:user:grant-role', ['phone' => '+21650999888', 'role' => 'ROLE_ADMIN']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--create', $tester->getDisplay());
        self::assertNull($this->user('+21650999888'));

        $tester = $this->runCommand('app:user:grant-role', ['phone' => '+21650999888', 'role' => 'ROLE_ADMIN', '--create' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $user = $this->user('+21650999888');
        self::assertSame(['ROLE_CLIENT', 'ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
        self::assertNotNull($user->getClientProfile());
        self::assertTrue($this->grants()[0]->getContext()['accountCreated']);

        // Le compte créé se connecte ensuite normalement par OTP.
        $tokens = $this->loginWithOtp('+21650999888');
        self::assertFalse($tokens['newAccount']);
        self::assertContains('ROLE_ADMIN', $tokens['roles']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidInputs(): iterable
    {
        yield 'numéro invalide' => ['12345', 'ROLE_ADMIN'];
        yield 'rôle inconnu' => ['+21650123456', 'ROLE_SUPERADMIN'];
        yield 'rôle professionnel' => ['+21650123456', 'ROLE_PROFESSIONAL'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(string $phone, string $role): void
    {
        Factory::client($this->em(), '+21650123456');

        $tester = $this->runCommand('app:user:grant-role', ['phone' => $phone, 'role' => $role]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame(['ROLE_CLIENT', 'ROLE_USER'], $this->user('+21650123456')->getRoles());
        self::assertSame([], $this->grants());
    }
}
