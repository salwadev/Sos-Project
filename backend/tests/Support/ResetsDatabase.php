<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Vide toutes les tables métier de la base de test (sos_test) avant chaque test.
 * Prérequis : php bin/console doctrine:migrations:migrate --env=test
 */
trait ResetsDatabase
{
    protected static function resetDatabase(EntityManagerInterface $em): void
    {
        $connection = $em->getConnection();
        $tables = $connection->fetchFirstColumn(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename NOT IN ('doctrine_migration_versions', 'spatial_ref_sys')",
        );
        if ([] !== $tables) {
            // TRUNCATE ne déclenche pas le trigger « ajout seul » du journal d'audit (déclencheur par ligne).
            $connection->executeStatement('TRUNCATE '.implode(', ', array_map(static fn (string $t) => '"'.$t.'"', $tables)).' CASCADE');
        }
        $em->clear();
    }
}
