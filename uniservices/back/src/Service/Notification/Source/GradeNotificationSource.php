<?php

namespace App\Service\Notification\Source;

use App\Entity\Etudiant\EtudiantNote;
use App\Entity\Users\Etudiant;
use App\Enum\EtatEvaluationEnum;
use App\Service\Notification\Notification;
use App\Service\Notification\StudentSemesters;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Une note d'une évaluation publiée. Une note saisie mais pas publiée ne se signale pas.
 */
final class GradeNotificationSource implements NotificationSourceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StudentSemesters $semesters,
    ) {
    }

    public function getNotifications(Etudiant $student, \DateTimeImmutable $since): iterable
    {
        $scolariteSemesters = $this->semesters->forStudent($student);
        if ([] === $scolariteSemesters) {
            return [];
        }

        /** @var list<EtudiantNote> $notes */
        $notes = $this->entityManager->createQueryBuilder()
            ->select('n', 'e', 'ens')
            ->from(EtudiantNote::class, 'n')
            ->join('n.evaluation', 'e')
            ->leftJoin('e.enseignement', 'ens')
            ->where('n.scolariteSemestre IN (:scolariteSemesters)')
            ->andWhere('e.etat = :publiee')
            ->setParameter('scolariteSemesters', $scolariteSemesters)
            ->setParameter('publiee', EtatEvaluationEnum::ETAT_PUBLIEE)
            ->getQuery()
            ->getResult();

        $notifications = [];
        foreach ($notes as $note) {
            $date = self::lastModified($note);
            if (null === $date || $date < $since) {
                continue;
            }

            $evaluation = $note->getEvaluation();
            $enseignement = $evaluation?->getEnseignement();
            $subjectName = null === $enseignement ? null : trim($enseignement->getCodeEnseignement().' '.$enseignement->getLibelle());

            $notifications[] = new Notification(
                'note-'.$note->getId(),
                Notification::TYPE_NOTE,
                null === $subjectName ? 'Nouvelle note' : 'Nouvelle note en '.$subjectName,
                sprintf('%s : %s', $evaluation?->getLibelle() ?? 'Évaluation', self::formatResult($note)),
                $date,
                '/intranet/scolarite',
            );
        }

        return $notifications;
    }

    /**
     * Même lecture que la page Notes et absences : le statut dit ce que vaut la note.
     */
    public static function formatResult(EtudiantNote $note): string
    {
        return match ($note->getPresenceStatut() ?? EtudiantNote::STATUT_PRESENT) {
            EtudiantNote::STATUT_ABSENT_INJUSTIFIE => 'absence injustifiée, compte 0',
            EtudiantNote::STATUT_ABSENT_JUSTIFIE => 'absence justifiée, ne compte pas',
            EtudiantNote::STATUT_DISPENSE => 'dispense, ne compte pas',
            default => null !== $note->getNote() && $note->getNote() >= 0
                ? str_replace('.', ',', (string) round($note->getNote(), 2)).' / 20'
                : 'note à venir',
        };
    }

    private static function lastModified(EtudiantNote $note): ?\DateTimeImmutable
    {
        $date = $note->getUpdated() ?? $note->getCreated();

        return null === $date ? null : \DateTimeImmutable::createFromInterface($date);
    }
}
