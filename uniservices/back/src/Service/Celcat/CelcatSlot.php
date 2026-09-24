<?php

namespace App\Service\Celcat;

/**
 * Un créneau daté, tel qu'il se déduit d'une ligne Celcat pour une semaine donnée.
 */
final readonly class CelcatSlot
{
    public function __construct(
        public int $celcatId,
        public int $semaine,
        public int $jour,
        public \DateTimeImmutable $date,
        public \DateTimeImmutable $debut,
        public \DateTimeImmutable $fin,
        public bool $estUnCours,
        public ?string $type,
        public string $codeModule,
        public string $libModule,
        public ?string $codePersonnel,
        public ?string $libPersonnel,
        public ?string $codeSalle,
        public ?string $libSalle,
        public ?string $codeGroupe,
        public ?string $libGroupe,
        public ?\DateTimeImmutable $modifieLe,
    ) {
    }

    /**
     * Même clé que EdtCelcat::getUniqueId() de l'intranet V3. L'identifiant Celcat ne suffit
     * pas : un cours décrit toutes ses semaines, et un CM commun à plusieurs groupes revient
     * une fois par groupe dans la jointure.
     */
    public function cle(): string
    {
        return $this->celcatId.'_'.$this->semaine.'_'.$this->jour.'_'.($this->codeGroupe ?? '');
    }
}
