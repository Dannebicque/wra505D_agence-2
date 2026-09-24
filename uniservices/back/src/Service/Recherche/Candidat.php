<?php

namespace App\Service\Recherche;

/**
 * Élément cherchable fourni par une source : ce qui est affiché, et le texte sur lequel on compare.
 */
final readonly class Candidat
{
    public function __construct(
        public string $type,
        public int $id,
        public string $libelle,
        public ?string $detail,
        public string $texte,
    ) {
    }
}
