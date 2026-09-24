<?php

namespace App\Service\Celcat;

/**
 * Ce que la synchronisation attend de Celcat. CelcatReader en est la seule implémentation ;
 * l'interface existe pour que les tests fournissent une source en mémoire.
 */
interface CelcatSource
{
    public function estConfigure(): bool;

    /**
     * @return array<int, \DateTimeImmutable> lundi de chaque semaine, indexé par numéro
     */
    public function lireSemaines(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function lireEvenements(int $departement): array;
}
