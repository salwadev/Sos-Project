<?php

namespace App\Tests\Integration;

use App\Audit\AuditLogger;
use App\Tests\Support\Factory;
use App\Tests\Support\ResetsDatabase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** §7.6 : journal d'audit chaîné et protégé contre l'altération. */
final class AuditLoggerTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $em;
    private AuditLogger $audit;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->audit = static::getContainer()->get(AuditLogger::class);
        self::resetDatabase($this->em);
    }

    public function testEntriesAreChainedAndVerifiable(): void
    {
        $admin = Factory::client($this->em);
        $first = $this->audit->record('professional.verified', $admin, 'ProfessionalProfile', 'p-1', ['status' => 'verified']);
        $second = $this->audit->record('commission.rate_changed', $admin, 'ServiceCategory', 'c-1', ['from' => 1000, 'to' => 1200]);

        self::assertNull($first->getPreviousHash());
        self::assertSame($first->getHash(), $second->getPreviousHash());

        $this->em->clear();
        self::assertSame([], $this->audit->verifyChain(), 'La chaîne relue depuis la base est intègre.');
    }

    public function testTheDatabaseRefusesToModifyAnEntry(): void
    {
        $this->audit->record('user.suspended', null, 'User', 'u-1', ['reason' => 'fraude']);

        $this->expectException(DbalException::class);
        $this->em->getConnection()->executeStatement("UPDATE audit_log SET action = 'user.reactivated'");
    }

    public function testTheDatabaseRefusesToDeleteAnEntry(): void
    {
        $this->audit->record('user.suspended', null, 'User', 'u-1');

        $this->expectException(DbalException::class);
        $this->em->getConnection()->executeStatement('DELETE FROM audit_log');
    }
}
