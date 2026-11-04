<?php

namespace IntranetBundle\Dto\Enseignement;

/**
 * @phpstan-import-type Revision from \IntranetBundle\Entity\Enseignement\AvancementEnseignement
 */
final class AvancementEnseignementHistoriqueDto
{
    /**
     * @param list<Revision> $items
     */
    public function __construct(public array $items, public ?int $avant)
    {
    }
}
