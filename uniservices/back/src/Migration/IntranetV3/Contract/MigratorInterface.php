<?php

declare(strict_types=1);

namespace App\Migration\IntranetV3\Contract;

use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;

/**
 * Migrateur d'une donnée de l'intranet V3 ; ses dépendances passent avant lui.
 */
interface MigratorInterface
{
    public function getName(): string;

    /**
     * @return list<class-string<MigratorInterface>>
     */
    public function getDependencies(): array;

    public function migrate(MigrationContext $context): MigrationResult;
}
