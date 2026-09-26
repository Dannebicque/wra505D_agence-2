<?php

declare(strict_types=1);

namespace App\Service\Celcat;

/**
 * Ce que la synchronisation attend de Celcat. CelcatReader en est la seule implémentation ;
 * l'interface existe pour que les tests fournissent une source en mémoire.
 */
interface CelcatSource
{
    public function isConfigured(): bool;

    /**
     * @return array<int, \DateTimeImmutable> lundi de chaque semaine, indexé par numéro
     */
    public function readWeeks(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function readEvents(int $department): array;
}
