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
     * Celcat décrit un cours une seule fois pour toutes ses semaines : c'est le couple
     * identifiant et semaine qui désigne un créneau précis.
     */
    public function cle(): string
    {
        return $this->celcatId.'-'.$this->semaine;
    }
}
