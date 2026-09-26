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
use App\Utils\LooseValue;

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

        foreach (LooseValue::rows($subjects) as $subject) {
            if (null === $subject['libelle']) {
                continue;
            }

            $type = $subject['type'] instanceof TypeEnseignementEnum ? $subject['type']->getLibelle() : null;
            $code = LooseValue::nullableString($subject['codeEnseignement']);
            $label = LooseValue::string($subject['libelle']);

            yield new Candidate(
                'enseignement',
                LooseValue::int($subject['id']),
                null === $code ? $label : $code.' '.$label,
                $type,
                implode(' ', array_filter([
                    $code,
                    $label,
                    LooseValue::nullableString($subject['libelle_court']),
                    LooseValue::nullableString($subject['motsCles']),
                ])),
            );
        }
    }
}
