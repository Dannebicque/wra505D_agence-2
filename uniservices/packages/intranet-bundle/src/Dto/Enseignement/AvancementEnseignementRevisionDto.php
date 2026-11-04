<?php

namespace IntranetBundle\Dto\Enseignement;

/**
 * @phpstan-import-type SequenceSnapshot from \IntranetBundle\Entity\Enseignement\AvancementEnseignement
 */
final class AvancementEnseignementRevisionDto
{
    /**
     * @param SequenceSnapshot|null $sequence
     */
    public function __construct(
        public int $id,
        public string $repere,
        public string $prochaineEtape,
        public ?array $sequence,
        public ?string $etatSequence,
        public string $auteur,
        public string $date,
    ) {
    }
}
