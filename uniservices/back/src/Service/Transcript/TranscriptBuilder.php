<?php

declare(strict_types=1);

namespace App\Service\Transcript;

use App\Entity\Etudiant\EtudiantNote;
use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Structure\StructureUe;
use App\Entity\Users\Etudiant;
use App\Enum\EtatEvaluationEnum;
use Doctrine\ORM\EntityManagerInterface;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use IntranetBundle\Enum\EtatJustificatifEnum;

/**
 * Relevé d'un étudiant, semestre par semestre, pour chaque année tenue par uniServices : UE,
 * matières, évaluations, notes publiées, moyennes provisoires, pénalités d'absence et absences.
 * Les années reprises de la V3 n'ont pas de semestre structuré ni de notes : elles n'y sont pas.
 *
 * Une note n'est montrée que si son évaluation est publiée : c'est le seul mécanisme de
 * publication que le code utilise, le drapeau EtudiantNote::$publiee n'étant jamais renseigné.
 */
final readonly class TranscriptBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GradeAverageCalculator $calculator,
    ) {
    }

    /**
     * @return array{anneeUniversitaire: ?string, semestres: list<array<string, mixed>>}
     */
    public function build(Etudiant $student): array
    {
        $enrollments = $this->entityManager->getRepository(EtudiantScolarite::class)->findBy(['etudiant' => $student]);
        usort($enrollments, static fn (EtudiantScolarite $a, EtudiantScolarite $b): int => ($a->getAnneeUniversitaire()?->getAnnee() ?? 0) <=> ($b->getAnneeUniversitaire()?->getAnnee() ?? 0));

        $activeYear = null;
        $semesters = [];
        foreach ($enrollments as $studentEnrollment) {
            if (true === $studentEnrollment->isActif()) {
                $activeYear = $studentEnrollment->getAnneeUniversitaire()?->getLibelle();
            }
            $enrollmentSemesters = $studentEnrollment->getScolariteSemestre()->toArray();
            usort($enrollmentSemesters, static fn (EtudiantScolariteSemestre $a, EtudiantScolariteSemestre $b): int => ($a->getSemestre()?->getOrdreLmd() ?? 0) <=> ($b->getSemestre()?->getOrdreLmd() ?? 0));
            foreach ($enrollmentSemesters as $enrollmentSemester) {
                if (null !== $enrollmentSemester->getSemestre()) {
                    $semesters[] = $this->semester($studentEnrollment, $enrollmentSemester);
                }
            }
        }

        return [
            'anneeUniversitaire' => $activeYear,
            'semestres' => $semesters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function semester(EtudiantScolarite $studentEnrollment, EtudiantScolariteSemestre $enrollmentSemester): array
    {
        $semester = $enrollmentSemester->getSemestre();
        $courses = $this->entityManager->getRepository(StructureUe::class)->findBy(['semestre' => $semester], ['numero' => 'ASC']);

        $teachingIds = [];
        foreach ($courses as $course) {
            foreach ($course->getEnseignementUes() as $teachingCourse) {
                if (null !== $teachingCourse->getEnseignement()?->getId()) {
                    $teachingIds[] = $teachingCourse->getEnseignement()->getId();
                }
            }
        }

        $evaluations = $this->evaluationsByTeaching($teachingIds, $studentEnrollment);
        $grades = $this->gradesByEvaluation($enrollmentSemester);
        $absences = $this->absences($enrollmentSemester);
        $penalty = $this->penalty($semester);

        $teachingAverages = [];
        $courseRows = [];
        foreach ($courses as $course) {
            $teachings = [];
            foreach ($course->getEnseignementUes() as $teachingCourse) {
                $teaching = $teachingCourse->getEnseignement();
                if (null === $teaching || null === $teaching->getId()) {
                    continue;
                }
                $id = $teaching->getId();
                $evaluationRows = [];
                $retainedGrades = [];
                foreach ($evaluations[$id] ?? [] as $evaluation) {
                    $grade = $grades[$evaluation->getId()] ?? null;
                    $row = $this->evaluation($evaluation, $grade);
                    if ('publiee' === $row['etat'] && null !== $grade) {
                        $retainedGrades[] = [
                            'note' => $this->value($grade),
                            'coefficient' => $evaluation->getCoeff(),
                            'statut' => $grade->getPresenceStatut(),
                        ];
                    }
                    $evaluationRows[] = $row;
                }
                $teachingAverages[$id] ??= $this->calculator->teachingAverage($retainedGrades);
                $penalizing = $absences['parEnseignement'][$id] ?? ['penalisantes' => 0, 'enAttente' => 0];
                $penalizingCount = $penalty['active'] ? $penalizing['penalisantes'] : 0;

                $teachings[] = [
                    'code' => $teaching->getCodeEnseignement(),
                    'libelle' => $teaching->getLibelle(),
                    'type' => $teaching->getType()->getLibelle(),
                    'coefficient' => $teachingCourse->getCoefficient(),
                    'moyenne' => $teachingAverages[$id],
                    'absencesPenalisantes' => $penalizingCount,
                    'absencesEnAttente' => $penalty['active'] ? $penalizing['enAttente'] : 0,
                    'penalite' => $penalizingCount * $penalty['points'],
                    'moyennePenalisee' => $this->calculator->penalizedAverage($teachingAverages[$id], $penalizingCount, $penalty['points']),
                    'evaluations' => $evaluationRows,
                ];
            }

            $courseRows[] = [
                'numero' => $course->getNumero(),
                'libelle' => $course->getLibelle(),
                'moyenne' => $this->courseAverage($teachings, 'moyennePenalisee'),
                'moyenneSansPenalite' => $this->courseAverage($teachings, 'moyenne'),
                'enseignements' => $teachings,
            ];
        }

        return [
            'id' => $enrollmentSemester->getId(),
            'libelle' => $semester?->getLibelle(),
            'anneeUniversitaire' => $studentEnrollment->getAnneeUniversitaire()?->getLibelle(),
            'actif' => true === $studentEnrollment->isActif(),
            'penalite' => $penalty,
            'ues' => $courseRows,
            'absences' => $absences['resume'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $teachings
     */
    private function courseAverage(array $teachings, string $field): ?float
    {
        return $this->calculator->courseAverage(array_map(
            static fn (array $row): array => [
                'moyenne' => is_float($row[$field]) ? $row[$field] : null,
                'coefficient' => is_float($row['coefficient']) ? $row['coefficient'] : 0.0,
            ],
            $teachings,
        ));
    }

    /**
     * Options du semestre, avec les valeurs par défaut du client quand elles n'ont jamais été réglées.
     *
     * @return array{active: bool, points: float}
     */
    private function penalty(?StructureSemestre $semester): array
    {
        $options = $semester?->getOpt() ?? [];

        return [
            'active' => (bool) ($options['opt_penalite_absence'] ?? true),
            'points' => is_numeric($options['penalite_absence'] ?? null) ? (float) $options['penalite_absence'] : 0.5,
        ];
    }

    /**
     * @param list<int> $teachingIds
     *
     * @return array<int|string, list<ScolEvaluation>>
     */
    private function evaluationsByTeaching(array $teachingIds, EtudiantScolarite $studentEnrollment): array
    {
        if ([] === $teachingIds) {
            return [];
        }

        $evaluations = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(ScolEvaluation::class, 'e')
            ->where('e.enseignement IN (:enseignements)')
            ->andWhere('e.anneeUniversitaire = :annee')
            ->andWhere('e.etat <> :annulee')
            ->setParameter('enseignements', array_unique($teachingIds))
            ->setParameter('annee', $studentEnrollment->getAnneeUniversitaire())
            ->setParameter('annulee', EtatEvaluationEnum::ETAT_ANNULEE)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();

        $byTeaching = [];
        foreach ($evaluations as $evaluation) {
            $byTeaching[$evaluation->getEnseignement()?->getId()][] = $evaluation;
        }

        return $byTeaching;
    }

    /**
     * @return array<int, EtudiantNote>
     */
    private function gradesByEvaluation(EtudiantScolariteSemestre $enrollmentSemester): array
    {
        $grades = [];
        foreach ($this->entityManager->getRepository(EtudiantNote::class)->findBy(['scolariteSemestre' => $enrollmentSemester]) as $grade) {
            if (null !== $grade->getEvaluation()?->getId()) {
                $grades[$grade->getEvaluation()->getId()] = $grade;
            }
        }

        return $grades;
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluation(ScolEvaluation $evaluation, ?EtudiantNote $grade): array
    {
        $published = EtatEvaluationEnum::ETAT_PUBLIEE === $evaluation->getEtat();
        $upcoming = null !== $evaluation->getDate() && $evaluation->getDate() > new \DateTimeImmutable('today');

        return [
            'libelle' => $evaluation->getLibelle(),
            'date' => $evaluation->getDate()?->format('Y-m-d'),
            'coefficient' => $evaluation->getCoeff(),
            'type' => $evaluation->getType()?->value,
            'etat' => $published ? 'publiee' : ($upcoming ? 'a_venir' : 'en_attente'),
            'note' => $published && null !== $grade ? $this->value($grade) : null,
            'statut' => $published ? ($grade?->getPresenceStatut() ?? EtudiantNote::STATUT_PRESENT) : null,
        ];
    }

    /**
     * Valeur d'une note présente et saisie. EtudiantNote::getNote() renvoie -0,01 pour une absence
     * justifiée ou une dispense, et 0 pour une absence injustifiée : c'est le statut qui le dit ici.
     */
    private function value(EtudiantNote $grade): ?float
    {
        if (EtudiantNote::STATUT_PRESENT !== $grade->getPresenceStatut()) {
            return null;
        }
        $value = $grade->getNote();

        return null !== $value && $value >= 0 ? $value : null;
    }

    /**
     * Le résumé des absences du semestre, et pour chaque enseignement celles qui le pénalisent :
     * toutes sauf les justifiées validées. Celles en attente de décision pénalisent jusqu'à
     * l'acceptation du justificatif, comme en V3, et sont comptées à part pour le dire.
     *
     * @return array{
     *     resume: array{total: int, justifiees: int, injustifiees: int, enAttente: int, liste: list<array<string, ?string>>},
     *     parEnseignement: array<int, array{penalisantes: int, enAttente: int}>
     * }
     */
    private function absences(EtudiantScolariteSemestre $enrollmentSemester): array
    {
        $absences = $this->entityManager->createQueryBuilder()
            ->select('a', 'ev', 'j')
            ->from(EtudiantAbsence::class, 'a')
            ->join('a.event', 'ev')
            ->leftJoin('a.absenceJustificatif', 'j')
            ->where('a.scolariteSemestre = :scolariteSemestre')
            ->setParameter('scolariteSemestre', $enrollmentSemester)
            ->orderBy('ev.debut', 'DESC')
            ->getQuery()
            ->getResult();

        $counters = ['validee' => 0, 'aucune' => 0, 'refusee' => 0, 'en_attente' => 0];
        $list = [];
        $byTeaching = [];
        foreach ($absences as $absence) {
            $justification = match ($absence->getAbsenceJustificatif()?->getEtat()) {
                EtatJustificatifEnum::VALIDE => 'validee',
                EtatJustificatifEnum::REFUSE => 'refusee',
                EtatJustificatifEnum::EN_ATTENTE => 'en_attente',
                default => 'aucune',
            };
            ++$counters[$justification];
            $event = $absence->getEvent();
            $teachingId = $event?->getEnseignement()?->getId();
            if (null !== $teachingId && 'validee' !== $justification) {
                $byTeaching[$teachingId] ??= ['penalisantes' => 0, 'enAttente' => 0];
                ++$byTeaching[$teachingId]['penalisantes'];
                if ('en_attente' === $justification) {
                    ++$byTeaching[$teachingId]['enAttente'];
                }
            }
            $matiere = trim(($event?->getCodeModule() ?? '').' '.($event?->getLibModule() ?? ''));
            $list[] = [
                'debut' => $event?->getDebut()?->format(\DateTimeInterface::ATOM),
                'fin' => $event?->getFin()?->format(\DateTimeInterface::ATOM),
                'matiere' => $matiere !== '' && $matiere !== '0' ? $matiere : null,
                'code' => $event?->getEnseignement()?->getCodeEnseignement(),
                'justification' => $justification,
            ];
        }

        return [
            'resume' => [
                'total' => count($list),
                'justifiees' => $counters['validee'],
                'injustifiees' => $counters['aucune'] + $counters['refusee'],
                'enAttente' => $counters['en_attente'],
                'liste' => $list,
            ],
            'parEnseignement' => $byTeaching,
        ];
    }
}
