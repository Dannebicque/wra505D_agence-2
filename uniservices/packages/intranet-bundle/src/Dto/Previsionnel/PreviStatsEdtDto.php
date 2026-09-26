<?php

namespace IntranetBundle\Dto\Previsionnel;

use Symfony\Component\Serializer\Attribute\Groups;

class PreviStatsEdtDto
{
    /** @var list<array<string, float|int|string>> */
    #[Groups(['previsionnel_stats_edt:read'])]
    protected array $statPreviEdtEnseignement = [];

    /** @var list<array<string, float|int|string>> */
    #[Groups(['previsionnel_stats_edt:read'])]
    protected array $statPreviEdtEnseignant = [];

    // Liste dynamique des types de groupes (CM, TD, TP, ...)
    /** @var list<string> */
    #[Groups(['previsionnel_stats_edt:read'])]
    protected array $typesGroupes = [];

    #[Groups(['previsionnel_stats_edt:read'])]
    protected int $taux_realisation = 0;

    /** @return list<array<string, float|int|string>> */
    public function getStatPreviEdtEnseignement(): array
    {
        return $this->statPreviEdtEnseignement;
    }

    /** @param list<array<string, float|int|string>> $statPreviEdtEnseignement */
    public function setStatPreviEdtEnseignement(array $statPreviEdtEnseignement): void
    {
        $this->statPreviEdtEnseignement = $statPreviEdtEnseignement;
    }

    /** @return list<array<string, float|int|string>> */
    public function getStatPreviEdtEnseignant(): array
    {
        return $this->statPreviEdtEnseignant;
    }

    /** @param list<array<string, float|int|string>> $statPreviEdtEnseignant */
    public function setStatPreviEdtEnseignant(array $statPreviEdtEnseignant): void
    {
        $this->statPreviEdtEnseignant = $statPreviEdtEnseignant;
    }

    /** @return list<string> */
    public function getTypesGroupes(): array
    {
        return $this->typesGroupes;
    }

    /** @param list<string> $typesGroupes */
    public function setTypesGroupes(array $typesGroupes): void
    {
        $this->typesGroupes = $typesGroupes;
    }

    public function getTauxRealisation(): int
    {
        return $this->taux_realisation;
    }

    public function setTauxRealisation(int $taux_realisation): void
    {
        $this->taux_realisation = $taux_realisation;
    }

}
