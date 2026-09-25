<?php

declare(strict_types=1);

namespace App\Service\Search\Source;

use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\TypeEnseignementEnum;
use App\Service\Search\Candidate;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ressources, SAÉ et matières des diplômes du département.
 *
 * C'est aussi l'entrée de l'emploi du temps dans la recherche : on trouve la matière, pas chacun
 * de ses créneaux.
 */
final readonly class SubjectSearchSource implements SearchSourceInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findCandidates(StructureDepartement $department, Etudiant|Personnel $user): iterable
    {
        // Un enseignement partagé entre plusieurs UE n'apparaît qu'une fois grâce au DISTINCT.
        $subjects = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT e.id', 'e.libelle', 'e.libelle_court', 'e.codeEnseignement', 'e.motsCles', 'e.type')
            ->from(ScolEnseignement::class, 'e')
            ->join('e.enseignementUes', 'eu')
            ->join('eu.ue', 'ue')
            ->join('ue.semestre', 'sem')
            ->join('sem.annee', 'annee')
            ->join('annee.pn', 'pn')
            ->join('pn.diplome', 'diplome')
            ->where('diplome.departement = :departement')
            ->setParameter('departement', $department)
            ->getQuery()
            ->getArrayResult();

        foreach ($subjects as $subject) {
            if (null === $subject['libelle']) {
                continue;
            }

            $type = $subject['type'] instanceof TypeEnseignementEnum ? $subject['type']->getLibelle() : null;
            $code = $subject['codeEnseignement'];

            yield new Candidate(
                'enseignement',
                $subject['id'],
                null === $code ? $subject['libelle'] : $code.' '.$subject['libelle'],
                $type,
                implode(' ', array_filter([
                    $code,
                    $subject['libelle'],
                    $subject['libelle_court'],
                    $subject['motsCles'],
                ])),
            );
        }
    }
}
