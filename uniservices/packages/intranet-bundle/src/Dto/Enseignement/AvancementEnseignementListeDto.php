<?php

namespace IntranetBundle\Dto\Enseignement;

/**
 * @phpstan-import-type Revision from \IntranetBundle\Entity\Enseignement\AvancementEnseignement
 * @phpstan-import-type SequenceSnapshot from \IntranetBundle\Entity\Enseignement\AvancementEnseignement
 */
final class AvancementEnseignementListeDto
{
    /**
     * @param list<SequenceSnapshot> $sequences
     * @param list<array{id: int|null, libelle: string|null, type: string, avancement: Revision|null}> $groupes
     */
    public function __construct(
        public int $anneeUniversitaire,
        public ?string $anneeLibelle,
        public array $sequences,
        public array $groupes,
    ) {
    }
}
