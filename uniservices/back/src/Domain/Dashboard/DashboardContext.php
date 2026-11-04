<?php

namespace App\Domain\Dashboard;

use App\Entity\Structure\StructureDepartementPersonnel;

final class DashboardContext
{
    public function __construct(
        private readonly ?int $departementId,
        private readonly ?StructureDepartementPersonnel $structureDepartementPersonnel = null,
        /** @var ?list<int|null> $diplomeIds */
        private readonly ?array $diplomeIds = null,
    ) {
    }

    /** null means no diploma restriction; otherwise providers must filter to these ids. */
    /** @return ?list<int|null> */
    public function getDiplomeIds(): ?array
    {
        return $this->diplomeIds;
    }

    public function getDepartementId(): ?int
    {
        return $this->departementId;
    }

    public function hasDepartement(): bool
    {
        return null !== $this->departementId;
    }

    public function getStructureDepartementPersonnel(): ?StructureDepartementPersonnel
    {
        return $this->structureDepartementPersonnel;
    }
}
