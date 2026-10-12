<?php

declare(strict_types=1);

namespace StageBundle\Migration\IntranetV3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/**
 * Vide les tables du bundle stage avant un nouvel import V3 du stage.
 */
final readonly class InternshipDatabaseResetter
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return int nombre de tables vidées
     */
    public function reset(): int
    {
        $tables = [];
        foreach ($this->connection->createSchemaManager()->introspectTableNames() as $name) {
            $table = $name->getUnqualifiedName()->getValue();
            if (str_starts_with($table, 'stage_')) {
                $tables[] = $table;
            }
        }
        if ($tables === []) {
            return 0;
        }
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            throw new \RuntimeException('La remise à zéro du stage ne sait vider qu\'une base MySQL ou MariaDB : rien n\'a été supprimé.');
        }

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                $this->connection->executeStatement('TRUNCATE TABLE ' . $this->connection->quoteSingleIdentifier($table));
            }
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return count($tables);
    }
}
