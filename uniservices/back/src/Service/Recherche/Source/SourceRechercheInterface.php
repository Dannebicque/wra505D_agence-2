<?php

namespace App\Service\Recherche\Source;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Recherche\Candidat;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un type d'élément cherchable. Ajouter un type à la recherche, c'est ajouter une source.
 */
#[AutoconfigureTag(self::TAG)]
interface SourceRechercheInterface
{
    public const TAG = 'app.source_recherche';

    /**
     * Les éléments que cet utilisateur a le droit de trouver dans son département.
     *
     * @return iterable<Candidat>
     */
    public function candidats(StructureDepartement $departement, Etudiant|Personnel $utilisateur): iterable;
}
