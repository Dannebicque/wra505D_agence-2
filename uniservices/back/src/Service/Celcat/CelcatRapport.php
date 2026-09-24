<?php

namespace App\Service\Celcat;

/**
 * Bilan d'une synchronisation, lu par la commande pour l'afficher.
 */
final class CelcatRapport
{
    public int $crees = 0;
    public int $misAJour = 0;
    public int $supprimes = 0;

    /** @var list<string> créneaux disparus de Celcat mais gardés, car ils portent des absences */
    public array $conserves = [];

    /** @var array<string, true> */
    public array $groupesInconnus = [];

    /** @var array<string, true> */
    public array $personnelsInconnus = [];

    /** @var array<string, true> */
    public array $modulesInconnus = [];
}
