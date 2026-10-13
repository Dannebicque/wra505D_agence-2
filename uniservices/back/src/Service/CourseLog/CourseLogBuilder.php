<?php

declare(strict_types=1);

namespace App\Service\CourseLog;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureUe;
use App\Entity\Users\Etudiant;
use Doctrine\ORM\EntityManagerInterface;
use IntranetBundle\Entity\PlanCours\PlanCours;

/**
 * Cahier de texte de l'année en cours d'un étudiant : évaluations et plans de cours des matières
 * de ses semestres.
 */
final readonly class CourseLogBuilder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CourseLogAssembler $assembler,
    ) {
    }

    /**
     * @return array{anneeUniversitaire: ?string, echeances: list<array<string, mixed>>, matieres: list<array<string, mixed>>}
     */
    public function build(Etudiant $student): array
    {
        $studentEnrollment = $this->entityManager->getRepository(EtudiantScolarite::class)
            ->findOneBy(['etudiant' => $student, 'actif' => true]);
        $teachingIds = null === $studentEnrollment ? [] : $this->teachingIds($studentEnrollment);
        if (null === $studentEnrollment || [] === $teachingIds) {
            return [
                'anneeUniversitaire' => $studentEnrollment?->getAnneeUniversitaire()?->getLibelle(),
                'echeances' => [],
                'matieres' => [],
            ];
        }

        $evaluations = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(ScolEvaluation::class, 'e')
            ->where('e.enseignement IN (:enseignements)')
            ->andWhere('e.anneeUniversitaire = :annee')
            ->setParameter('enseignements', $teachingIds)
            ->setParameter('annee', $studentEnrollment->getAnneeUniversitaire())
            ->getQuery()
            ->getResult();

        $plans = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PlanCours::class, 'p')
            ->where('p.enseignement IN (:enseignements)')
            ->andWhere('p.anneeUniversitaire = :annee')
            ->setParameter('enseignements', $teachingIds)
            ->setParameter('annee', $studentEnrollment->getAnneeUniversitaire())
            ->getQuery()
            ->getResult();

        return [
            'anneeUniversitaire' => $studentEnrollment->getAnneeUniversitaire()?->getLibelle(),
            'echeances' => $this->assembler->deadlines($evaluations, new \DateTimeImmutable('today')),
            'matieres' => $this->assembler->subjects($plans),
        ];
    }

    /**
     * @return list<int>
     */
    private function teachingIds(EtudiantScolarite $studentEnrollment): array
    {
        $ids = [];
        foreach ($studentEnrollment->getScolariteSemestre() as $enrollmentSemester) {
            $semester = $enrollmentSemester->getSemestre();
            if (null === $semester) {
                continue;
            }
            foreach ($this->entityManager->getRepository(StructureUe::class)->findBy(['semestre' => $semester]) as $course) {
                foreach ($course->getEnseignementUes() as $teachingCourse) {
                    $id = $teachingCourse->getEnseignement()?->getId();
                    if (null !== $id) {
                        $ids[$id] = $id;
                    }
                }
            }
        }

        return array_values($ids);
    }
}
