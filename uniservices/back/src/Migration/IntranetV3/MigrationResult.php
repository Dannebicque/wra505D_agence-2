<?php

declare(strict_types=1);

namespace App\Migration\IntranetV3;

/**
 * Bilan d'un migrateur ou d'un initialiseur : lignes créées, mises à jour, ignorées, en échec.
 */
final readonly class MigrationResult
{
    /**
     * @param list<string> $messages
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public array $messages = [],
    ) {
    }

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped + $this->failed;
    }
}
