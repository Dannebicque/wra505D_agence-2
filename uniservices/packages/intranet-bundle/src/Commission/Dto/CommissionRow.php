<?php

namespace IntranetBundle\Commission\Dto;

final class CommissionRow implements \JsonSerializable
{
    /**
     * @param array<string, float|null> $cells valeurs indexées par clé de colonne
     */
    public function __construct(
        public readonly int $scolariteSemestreId,
        public readonly ?int $etudiantId,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly array $cells,
        public readonly ?float $moyenne,
        public ?int $rang = null,
        public readonly ?bool $decision = null,
        public readonly string $decisionLibelle = '',
        public readonly int $uesValidees = 0,
        public readonly ?string $numeroEtudiant = null,
        public readonly ?string $dateNaissance = null,
        public readonly ?string $bac = null,
    ) {
    }

    /**
     * @return array{
     *     scolariteSemestreId: int,
     *     etudiantId: int|null,
     *     nom: string,
     *     prenom: string,
     *     cells: array<string, float|null>,
     *     moyenne: float|null,
     *     rang: int|null,
     *     decision: bool|null,
     *     decisionLibelle: string,
     *     uesValidees: int,
     *     numeroEtudiant: string|null,
     *     dateNaissance: string|null,
     *     bac: string|null
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'scolariteSemestreId' => $this->scolariteSemestreId,
            'etudiantId' => $this->etudiantId,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'cells' => $this->cells,
            'moyenne' => $this->moyenne,
            'rang' => $this->rang,
            'decision' => $this->decision,
            'decisionLibelle' => $this->decisionLibelle,
            'uesValidees' => $this->uesValidees,
            'numeroEtudiant' => $this->numeroEtudiant,
            'dateNaissance' => $this->dateNaissance,
            'bac' => $this->bac,
        ];
    }
}
