<?php

declare(strict_types=1);

namespace App\Migration\IntranetV3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/**
 * Vide les tables de l'application avant un nouvel import V3, sans toucher au schéma.
 */
final readonly class DatabaseResetter
{
    private const LOCK_WAIT_TIMEOUT_SECONDS = 5;

    private const PRESERVED_TABLES = [
        'doctrine_migration_versions',
    ];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * Vide les tables plutôt que de recréer la base : le schéma reste prêt pour l'import.
     *
     * @param null|callable(string): void $onTableReset
     *
     * @return int nombre de tables vidées
     */
    public function reset(?callable $onTableReset = null): int
    {
        $tables = [];
        foreach ($this->connection->createSchemaManager()->introspectTableNames() as $name) {
            $table = $name->getUnqualifiedName()->getValue();
            if (!in_array($table, self::PRESERVED_TABLES, true)) {
                $tables[] = $table;
            }
        }

        if ($tables === []) {
            return 0;
        }

        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            throw new \RuntimeException('La remise à zéro ne sait vider qu\'une base MySQL ou MariaDB : rien n\'a été supprimé.');
        }

        return $this->resetMySql($tables, $onTableReset);
    }

    /**
     * @param list<string>                $tables
     * @param null|callable(string): void $onTableReset
     */
    private function resetMySql(array $tables, ?callable $onTableReset): int
    {
        // TRUNCATE prend un verrou de métadonnées : sans délai de session, une autre connexion qui
        // utilise une table ferait paraître la commande figée indéfiniment.
        $this->connection->executeStatement(sprintf('SET SESSION lock_wait_timeout = %d', self::LOCK_WAIT_TIMEOUT_SECONDS));
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                if ($onTableReset !== null) {
                    $onTableReset($table);
                }
                $this->connection->executeStatement('TRUNCATE TABLE ' . $this->connection->quoteSingleIdentifier($table));
            }
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return count($tables);
    }
}
