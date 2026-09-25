<?php

declare(strict_types=1);

namespace App\Service\Search;

/**
 * Élément cherchable fourni par une source : ce qui est affiché, le texte sur lequel on compare, et
 * l'adresse mail universitaire quand l'élément est une personne.
 */
final readonly class Candidate
{
    public function __construct(
        public string $type,
        public int $id,
        public string $label,
        public ?string $details,
        public string $text,
        public ?string $email = null,
    ) {
    }
}
