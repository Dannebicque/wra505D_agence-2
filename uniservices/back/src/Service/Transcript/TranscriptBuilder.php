<?php

declare(strict_types=1);

namespace App\Service\Transcript;

use App\Entity\Etudiant\EtudiantNote;
use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureUe;
use App\Entity\Users\Etudiant;
use App\Enum\EtatEvaluationEnum;
use Doctrine\ORM\EntityManagerInterface;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use IntranetBundle\Enum\EtatJustificatifEnum;

/**
 * Relevé de l'année en cours d'un étudiant : UE, matières, évaluations, notes publiées, moyennes
 * provisoires et absences, semestre par semestre.
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
        $studentEnrollment = $this->entityManager->getRepository(EtudiantScolarite::class)
            ->findOneBy(['etudiant' => $student, 'actif' => true]);
        if (null === $studentEnrollment) {
            return ['anneeUniversitaire' => null, 'semestres' => []];
        }

        $semesters = [];
        foreach ($studentEnrollment->getScolariteSemestre() as $enrollmentSemester) {
            if (null !== $enrollmentSemester->getSemestre()) {
                $semesters[] = $this->semester($studentEnrollment, $enrollmentSemester);
            }
        }

        return [
            'anneeUniversitaire' => $studentEnrollment->getAnneeUniversitaire()?->getLibelle(),
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

                $teachings[] = [
                    'code' => $teaching->getCodeEnseignement(),
                    'libelle' => $teaching->getLibelle(),
                    'type' => $teaching->getType()->getLibelle(),
                    'coefficient' => $teachingCourse->getCoefficient(),
                    'moyenne' => $teachingAverages[$id],
                    'evaluations' => $evaluationRows,
                ];
            }

            $courseRows[] = [
                'numero' => $course->getNumero(),
                'libelle' => $course->getLibelle(),
                'moyenne' => $this->calculator->courseAverage(array_map(
                    fn (array $row) => ['moyenne' => $row['moyenne'], 'coefficient' => $row['coefficient'] ?? 0.0],
                    $teachings,
                )),
                'enseignements' => $teachings,
            ];
        }

        return [
            'libelle' => $semester?->getLibelle(),
            'ues' => $courseRows,
            'absences' => $this->absences($enrollmentSemester),
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
     * @return array{total: int, justifiees: int, injustifiees: int, enAttente: int, liste: list<array<string, ?string>>}
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
        foreach ($absences as $absence) {
            $justification = match ($absence->getAbsenceJustificatif()?->getEtat()) {
                EtatJustificatifEnum::VALIDE => 'validee',
                EtatJustificatifEnum::REFUSE => 'refusee',
                EtatJustificatifEnum::EN_ATTENTE => 'en_attente',
                default => 'aucune',
            };
            ++$counters[$justification];
            $event = $absence->getEvent();
            $list[] = [
                'debut' => $event?->getDebut()?->format(\DateTimeInterface::ATOM),
                'fin' => $event?->getFin()?->format(\DateTimeInterface::ATOM),
                'matiere' => trim(($event?->getCodeModule() ?? '').' '.($event?->getLibModule() ?? '')) ?: null,
                'justification' => $justification,
            ];
        }

        return [
            'total' => count($list),
            'justifiees' => $counters['validee'],
            'injustifiees' => $counters['aucune'] + $counters['refusee'],
            'enAttente' => $counters['en_attente'],
            'liste' => $list,
        ];
    }
}
