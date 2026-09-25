<?php

declare(strict_types=1);

namespace App\Service\Celcat;

/**
 * Bilan d'une synchronisation, lu par la commande pour l'afficher.
 */
final class CelcatReport
{
    public int $created = 0;
    public int $updated = 0;
    public int $deleted = 0;

    /** @var list<string> créneaux disparus de Celcat mais gardés, car ils portent des absences */
    public array $kept = [];

    /** @var array<string, true> */
    public array $unknownGroups = [];

    /** @var array<string, true> */
    public array $unknownStaff = [];

    /** @var array<string, true> */
    public array $unknownModules = [];
}
