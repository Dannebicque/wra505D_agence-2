<?php

namespace App\ApiDto\Edt;

use Symfony\Component\Serializer\Attribute\Groups;

class EdtStatsPreviDto
{
    /**
     * Répartition des heures par enseignement.
     * Format: { '<libellé>' : { id: <enseignementId|null>, heures: <float> }, ... }
     */
    /** @var array<string, array{id: int|null, heures: float}> */
    #[Groups(['edt_stats:read'])]
    protected array $repartitionEnseignements = [];

    /**
     * Répartition des heures par enseignant.
     * Exemple : [ ['enseignant' => 'xxx', 'heures' => 12.5, 'pourcentage' => 30.5], ... ]
     */
    /** @var list<array{enseignant: string, heures: float, pourcentage: float}> */
    #[Groups(['edt_stats:read'])]
    protected array $repartitionEnseignants = [];

    /** @return array<string, array{id: int|null, heures: float}> */
    public function getRepartitionEnseignements(): array
    {
        return $this->repartitionEnseignements;
    }

    /** @param array<string, array{id: int|null, heures: float}> $repartitionEnseignements */
    public function setRepartitionEnseignements(array $repartitionEnseignements): void
    {
        $this->repartitionEnseignements = $repartitionEnseignements;
    }

    /** @return list<array{enseignant: string, heures: float, pourcentage: float}> */
    public function getRepartitionEnseignants(): array
    {
        return $this->repartitionEnseignants;
    }

    /** @param list<array{enseignant: string, heures: float, pourcentage: float}> $repartitionEnseignants */
    public function setRepartitionEnseignants(array $repartitionEnseignants): void
    {
        $this->repartitionEnseignants = $repartitionEnseignants;
    }
}
