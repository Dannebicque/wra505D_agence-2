<?php

declare(strict_types=1);

namespace App\Service\Search\Source;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Search\Candidate;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un type d'élément cherchable. Ajouter un type à la recherche, c'est ajouter une source.
 */
#[AutoconfigureTag(self::TAG)]
interface SearchSourceInterface
{
    public const string TAG = 'app.search_source';

    /**
     * Les éléments que cet utilisateur a le droit de trouver dans son département.
     *
     * @return iterable<Candidate>
     */
    public function findCandidates(StructureDepartement $department, Etudiant|Personnel $user): iterable;
}
