<?php

declare(strict_types=1);

namespace App\Service\Celcat;

/**
 * Un créneau daté, tel qu'il se déduit d'une ligne Celcat pour une semaine donnée.
 */
final readonly class CelcatSlot
{
    public function __construct(
        public int $celcatId,
        public int $week,
        public int $day,
        public \DateTimeImmutable $date,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $isCourse,
        public ?string $type,
        public string $moduleCode,
        public string $moduleLabel,
        public ?string $staffCode,
        public ?string $staffLabel,
        public ?string $roomCode,
        public ?string $roomLabel,
        public ?string $groupCode,
        public ?string $groupLabel,
        public ?\DateTimeImmutable $changedAt,
    ) {
    }

    /**
     * Même clé que EdtCelcat::getUniqueId() de l'intranet V3. L'identifiant Celcat ne suffit
     * pas : un cours décrit toutes ses semaines, et un CM commun à plusieurs groupes revient
     * une fois par groupe dans la jointure.
     */
    public function key(): string
    {
        return $this->celcatId.'_'.$this->week.'_'.$this->day.'_'.($this->groupCode ?? '');
    }
}
