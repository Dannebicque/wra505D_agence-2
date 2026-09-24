<?php

namespace App\Service\Recherche\Source;

use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\TypeEnseignementEnum;
use App\Service\Recherche\Candidat;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ressources, SAÉ et matières des diplômes du département.
 *
 * C'est aussi l'entrée de l'emploi du temps dans la recherche : on trouve la matière, pas chacun
 * de ses créneaux.
 */
final class SourceEnseignements implements SourceRechercheInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function candidats(StructureDepartement $departement, Etudiant|Personnel $utilisateur): iterable
    {
        // Un enseignement partagé entre plusieurs UE n'apparaît qu'une fois grâce au DISTINCT.
        $enseignements = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT e.id', 'e.libelle', 'e.libelle_court', 'e.codeEnseignement', 'e.motsCles', 'e.type')
            ->from(ScolEnseignement::class, 'e')
            ->join('e.enseignementUes', 'eu')
            ->join('eu.ue', 'ue')
            ->join('ue.semestre', 'sem')
            ->join('sem.annee', 'annee')
            ->join('annee.pn', 'pn')
            ->join('pn.diplome', 'diplome')
            ->where('diplome.departement = :departement')
            ->setParameter('departement', $departement)
            ->getQuery()
            ->getArrayResult();

        foreach ($enseignements as $enseignement) {
            if (null === $enseignement['libelle']) {
                continue;
            }

            $type = $enseignement['type'] instanceof TypeEnseignementEnum ? $enseignement['type']->getLibelle() : null;
            $code = $enseignement['codeEnseignement'];

            yield new Candidat(
                'enseignement',
                $enseignement['id'],
                null === $code ? $enseignement['libelle'] : $code.' '.$enseignement['libelle'],
                $type,
                implode(' ', array_filter([
                    $code,
                    $enseignement['libelle'],
                    $enseignement['libelle_court'],
                    $enseignement['motsCles'],
                ])),
            );
        }
    }
}
