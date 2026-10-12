<?php

declare(strict_types=1);

namespace App\Initialization;

use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;

/**
 * Donnée de la V4 posée avant l'import V3, comme l'établissement principal.
 */
interface InitializerInterface
{
    public function getName(): string;

    public function initialize(MigrationContext $context): MigrationResult;
}
