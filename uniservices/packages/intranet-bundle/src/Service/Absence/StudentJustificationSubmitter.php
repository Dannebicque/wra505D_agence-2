<?php

declare(strict_types=1);

namespace IntranetBundle\Service\Absence;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Users\Etudiant;
use Doctrine\ORM\EntityManagerInterface;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use IntranetBundle\Entity\Etudiant\EtudiantAbsenceJustificatif;
use IntranetBundle\Enum\EtatJustificatifEnum;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Dépôt d'un justificatif par l'étudiant lui-même : il part en attente de décision, rattaché aux
 * absences de la période. Une absence dont le justificatif a été refusé peut être justifiée de
 * nouveau : elle passe au nouveau dépôt.
 */
readonly class StudentJustificationSubmitter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JustificationFiles $files,
        private JustificatifReconciliationService $reconciliation,
    ) {
    }

    /**
     * Le semestre auquel rattacher la période : celui de ses absences, sinon le dernier semestre de
     * l'année en cours. Null si l'étudiant n'a aucune inscription active.
     */
    public function semesterFor(Etudiant $student, \DateTimeImmutable $start, \DateTimeImmutable $end): ?EtudiantScolariteSemestre
    {
        foreach ($this->absences($student, $start, $end) as $absence) {
            if (null !== $absence->getScolariteSemestre()) {
                return $absence->getScolariteSemestre();
            }
        }

        $enrollment = $this->entityManager->getRepository(EtudiantScolarite::class)->findOneBy(['etudiant' => $student, 'actif' => true]);
        $semesters = array_filter(
            $enrollment?->getScolariteSemestre()->toArray() ?? [],
            static fn (EtudiantScolariteSemestre $semester): bool => null !== $semester->getSemestre(),
        );
        usort($semesters, static fn (EtudiantScolariteSemestre $a, EtudiantScolariteSemestre $b): int => ($a->getSemestre()?->getOrdreLmd() ?? 0) <=> ($b->getSemestre()?->getOrdreLmd() ?? 0));

        return [] === $semesters ? null : end($semesters);
    }

    public function submit(
        Etudiant $student,
        EtudiantScolariteSemestre $semester,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $reason,
        UploadedFile $file,
    ): EtudiantAbsenceJustificatif {
        $justification = new EtudiantAbsenceJustificatif();
        $justification->setDebut(\DateTime::createFromImmutable($start));
        $justification->setFin(\DateTime::createFromImmutable($end));
        $justification->setMotif($reason);
        $justification->setEtat(EtatJustificatifEnum::EN_ATTENTE);
        $justification->setScolariteSemestre($semester);
        $justification->setNomFichier(mb_substr($file->getClientOriginalName(), 0, 255));
        $this->files->store($file, $justification);

        foreach ($this->absences($student, $start, $end) as $absence) {
            if (EtatJustificatifEnum::REFUSE === $absence->getAbsenceJustificatif()?->getEtat()) {
                $absence->setAbsenceJustificatif(null);
            }
        }

        $this->entityManager->persist($justification);
        $this->entityManager->flush();
        // L'écran du personnel ouvre la pièce par ce lien : il passe par le contrôle d'accès.
        $justification->setFichier(sprintf('/api/justificatifs/%d/fichier', $justification->getId()));
        $this->entityManager->flush();
        $this->reconciliation->reconcilierPourJustificatif($justification);
        // Le rattachement écrit côté absence : la collection du justificatif se relit en base.
        $this->entityManager->refresh($justification);

        return $justification;
    }

    /**
     * @return list<EtudiantAbsence>
     */
    private function absences(Etudiant $student, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var list<EtudiantAbsence> */
        return $this->entityManager->createQueryBuilder()
            ->select('absence')
            ->from(EtudiantAbsence::class, 'absence')
            ->join('absence.scolariteSemestre', 'scolariteSemestre')
            ->join('scolariteSemestre.scolarite', 'scolarite')
            ->join('absence.event', 'event')
            ->where('scolarite.etudiant = :etudiant')
            ->andWhere('event.debut <= :fin')
            ->andWhere('event.fin >= :debut')
            ->setParameter('etudiant', $student)
            ->setParameter('debut', $start)
            ->setParameter('fin', $end)
            ->getQuery()
            ->getResult();
    }
}
