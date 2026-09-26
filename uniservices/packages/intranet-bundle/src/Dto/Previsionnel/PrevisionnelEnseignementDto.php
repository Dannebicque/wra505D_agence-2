<?php

namespace IntranetBundle\Dto\Previsionnel;

use App\Entity\Users\Personnel;
use App\Enum\TypeEnseignementEnum;
use Symfony\Component\Serializer\Attribute\Groups;

class PrevisionnelEnseignementDto
{
    #[Groups(['previsionnel_enseignement:read'])]
    protected string $libelle;
    #[Groups(['previsionnel_enseignement:read'])]
    protected Personnel $personnel;
    #[Groups(['previsionnel_enseignement:read'])]
    protected TypeEnseignementEnum $typeEnseignement;
    /** @var array<string, array<string, float|int>> */
    #[Groups(['previsionnel_enseignement:read'])]
    protected array $heures = [];

    public function getPersonnel(): Personnel
    {
        return $this->personnel;
    }

    public function setPersonnel(Personnel $personnel): void
    {
        $this->personnel = $personnel;
    }

    public function getTypeEnseignement(): TypeEnseignementEnum
    {
        return $this->typeEnseignement;
    }

    public function setTypeEnseignement(TypeEnseignementEnum $typeEnseignement): void
    {
        $this->typeEnseignement = $typeEnseignement;
    }

    /** @return array<string, array<string, float|int>> */
    public function getHeures(): array
    {
        return $this->heures;
    }

    /** @param array<string, array<string, float|int>> $heures */
    public function setHeures(array $heures): void
    {
        $this->heures = $heures;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): void
    {
        $this->libelle = $libelle;
    }
}
