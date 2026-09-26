<?php

namespace App\ApiDto\Etudiant;

use Symfony\Component\Serializer\Attribute\Groups;

class EtudiantTrombinoscopeDto
{
    /** @var array<string, mixed> */
    #[Groups(['scolarite-semestre:manage-groupes'])]
    protected array $etudiant = [];

    /** @var list<array<string, mixed>> */
    #[Groups(['scolarite-semestre:manage-groupes'])]
    protected array $groupes = [];

    #[Groups(['scolarite-semestre:manage-groupes'])]
    protected int $id = 0;

    /** @return array<string, mixed> */
    public function getEtudiant(): array
    {
        return $this->etudiant;
    }

    /** @param array<string, mixed> $etudiant */
    public function setEtudiant(array $etudiant): void
    {
        $this->etudiant = $etudiant;
    }

    /** @return list<array<string, mixed>> */
    public function getGroupes(): array
    {
        return $this->groupes;
    }

    /** @param list<array<string, mixed>> $groupes */
    public function setGroupes(array $groupes): void
    {
        $this->groupes = $groupes;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
