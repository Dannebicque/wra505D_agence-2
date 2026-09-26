<?php

declare(strict_types=1);

namespace App\Service\Search\Source;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Search\Candidate;
use Doctrine\ORM\EntityManagerInterface;
use App\Utils\LooseValue;

/**
 * Étudiants inscrits cette année dans le département, comme dans la recherche de l'intranet V3.
 */
final readonly class StudentSearchSource implements SearchSourceInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findCandidates(StructureDepartement $department, Etudiant|Personnel $user): iterable
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('e.id', 'e.nom', 'e.prenom', 'e.username', 'e.mailUniv', 'sem.libelle AS semestre')
            ->from(EtudiantScolarite::class, 's')
            ->join('s.etudiant', 'e')
            ->leftJoin('s.scolariteSemestre', 'ss')
            ->leftJoin('ss.semestre', 'sem')
            ->where('s.departement = :departement')
            ->andWhere('s.actif = true')
            ->setParameter('departement', $department)
            ->getQuery()
            ->getArrayResult();

        // Une ligne par semestre : on regroupe par étudiant.
        $students = [];
        $semesters = [];
        foreach (LooseValue::rows($rows) as $row) {
            $id = LooseValue::key($row['id']);
            $students[$id] ??= $row;
            $semesters[$id] ??= [];
            if (null !== $row['semestre']) {
                $semesters[$id][] = LooseValue::string($row['semestre']);
            }
        }

        foreach ($students as $id => $student) {
            yield new Candidate(
                'etudiant',
                LooseValue::int($student['id']),
                LooseValue::castString($student['prenom']).' '.LooseValue::castString($student['nom']),
                [] === $semesters[$id] ? null : implode(', ', array_unique($semesters[$id])),
                LooseValue::castString($student['prenom']).' '.LooseValue::castString($student['nom']).' '.LooseValue::castString($student['username']),
                LooseValue::nullableString($student['mailUniv']),
            );
        }
    }
}
