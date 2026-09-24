<?php

namespace App\Service\Recherche;

/**
 * Élément cherchable fourni par une source : ce qui est affiché, le texte sur lequel on compare, et
 * l'adresse mail universitaire quand l'élément est une personne.
 */
final readonly class Candidat
{
    public function __construct(
        public string $type,
        public int $id,
        public string $libelle,
        public ?string $detail,
        public string $texte,
        public ?string $mail = null,
    ) {
    }
}
