<?php

namespace App\ApiDto\Edt;

use Symfony\Component\Serializer\Attribute\Groups;

class EdtStatsDto
{
    #[Groups(['edt_stats:read'])]
    protected float $totalHeures = 0.0;

    /**
     * Heures agrégées par type (clé => heures). Exemple: ['CM' => 12.5, 'TD' => 8]
     */
    /** @var array<string, float> */
    #[Groups(['edt_stats:read'])]
    protected array $heuresParType = [];

    /**
     * Répartition des heures par type d'activité (tableau d'objets avec pourcentage).
     * Exemple : [ ['type' => 'CM', 'heures' => 12.5, 'pourcentage' => 30.5], ... ]
     */
    /** @var list<array{type: string, heures: float, pourcentage: float}> */
    #[Groups(['edt_stats:read'])]
    protected array $repartitionTypes = [];

    /**
     * Répartition des heures par semestre.
     * Exemple : [ ['semestre' => 'S1', 'heures' => 12.5, 'pourcentage' => 30.5], ... ]
     */
    /** @var list<array{semestre: string, heures: float, pourcentage: float}> */
    #[Groups(['edt_stats:read'])]
    protected array $repartitionSemestres = [];

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
    /** @var array<string, float> */
    #[Groups(['edt_stats:read'])]
    protected array $repartitionEnseignants = [];

    /**
     * data pour export des heures
     */
    /** @var list<array<string, int|string|null>> */
    #[Groups(['edt_stats:read'])]
    protected array $export_data = [];

    public function getTotalHeures(): float
    {
        return $this->totalHeures;
    }

    public function setTotalHeures(float $totalHeures): void
    {
        $this->totalHeures = $totalHeures;
    }

    /** @return array<string, float> */
    public function getHeuresParType(): array
    {
        return $this->heuresParType;
    }

    /** @param array<string, float> $heuresParType */
    public function setHeuresParType(array $heuresParType): void
    {
        $this->heuresParType = $heuresParType;
    }

    /** @return list<array{type: string, heures: float, pourcentage: float}> */
    public function getRepartitionTypes(): array
    {
        return $this->repartitionTypes;
    }

    /** @param list<array{type: string, heures: float, pourcentage: float}> $repartitionTypes */
    public function setRepartitionTypes(array $repartitionTypes): void
    {
        $this->repartitionTypes = $repartitionTypes;
    }

    /** @return list<array{semestre: string, heures: float, pourcentage: float}> */
    public function getRepartitionSemestres(): array
    {
        return $this->repartitionSemestres;
    }

    /** @param list<array{semestre: string, heures: float, pourcentage: float}> $repartitionSemestres */
    public function setRepartitionSemestres(array $repartitionSemestres): void
    {
        $this->repartitionSemestres = $repartitionSemestres;
    }

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

    /** @return array<string, float> */
    public function getRepartitionEnseignants(): array
    {
        return $this->repartitionEnseignants;
    }

    /** @param array<string, float> $repartitionEnseignants */
    public function setRepartitionEnseignants(array $repartitionEnseignants): void
    {
        $this->repartitionEnseignants = $repartitionEnseignants;
    }

    /** @return list<array<string, int|string|null>> */
    public function getExportData(): array
    {
        return $this->export_data;
    }

    /** @param list<array<string, int|string|null>> $export_data */
    public function setExportData(array $export_data): void
    {
        $this->export_data = $export_data;
    }

}
