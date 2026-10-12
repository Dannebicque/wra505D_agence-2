<?php

declare(strict_types=1);

namespace DocumentBundle\Migration\IntranetV3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/**
 * Retire les documents et catégories venus de l'intranet V3 avant un nouvel import.
 */
final readonly class DocumentDatabaseResetter
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return int nombre de lignes supprimées
     */
    public function reset(): int
    {
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            throw new \RuntimeException('Le reset Document est actuellement prévu pour MySQL/MariaDB.');
        }
        $existing = [];
        foreach ($this->connection->createSchemaManager()->introspectTableNames() as $name) {
            $existing[] = $name->getUnqualifiedName()->getValue();
        }

        $count = 0;
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            // Ne jamais supprimer les documents natifs V4 : seuls les enregistrements
            // provenant de V3 sont identifiables de manière sûre par old_id.
            if (in_array('document', $existing, true)) {
                $count += (int) $this->connection->executeStatement('DELETE FROM document WHERE old_id IS NOT NULL');
            }
            if (in_array('document_category', $existing, true)) {
                $count += (int) $this->connection->executeStatement('DELETE FROM document_category WHERE old_id IS NOT NULL');
            }
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $count;
    }
}
