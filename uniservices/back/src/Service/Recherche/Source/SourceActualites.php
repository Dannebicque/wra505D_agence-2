<?php

namespace App\Service\Recherche\Source;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\TypePublicEnum;
use App\Repository\DepartementActualiteRepository;
use App\Service\Recherche\Candidat;

/**
 * Actualités du département destinées à l'utilisateur, selon la même règle que le tableau de bord.
 */
final class SourceActualites implements SourceRechercheInterface
{
    public function __construct(private readonly DepartementActualiteRepository $actualiteRepository)
    {
    }

    public function candidats(StructureDepartement $departement, Etudiant|Personnel $utilisateur): iterable
    {
        $public = $utilisateur instanceof Etudiant
            ? TypePublicEnum::TYPE_PUBLIC_ETUDIANT
            : TypePublicEnum::TYPE_PUBLIC_PERSONNEL;

        foreach ($this->actualiteRepository->findByDepartementAndPublic($departement, $public->value) as $actualite) {
            $libelle = $actualite->getLibelle();
            if (null === $libelle || null === $actualite->getId()) {
                continue;
            }

            yield new Candidat(
                'actualite',
                $actualite->getId(),
                $libelle,
                $actualite->getDateDebut()?->format('d/m/Y'),
                $libelle.' '.$actualite->getDescription(),
            );
        }
    }
}
