<?php

namespace App\Service\Notification\Source;

use App\Entity\Users\Etudiant;
use App\Service\Notification\Notification;
use App\Service\Notification\StudentSemesters;
use Doctrine\ORM\EntityManagerInterface;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use IntranetBundle\Enum\EtatJustificatifEnum;

/**
 * Une absence enregistrée, avec où en est son justificatif. Le justificatif ne porte pas de date
 * de décision : son état figure dans le texte, sans notification à part.
 */
final class AbsenceNotificationSource implements NotificationSourceInterface
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

        /** @var list<EtudiantAbsence> $absences */
        $absences = $this->entityManager->createQueryBuilder()
            ->select('a', 'ev', 'j')
            ->from(EtudiantAbsence::class, 'a')
            ->leftJoin('a.event', 'ev')
            ->leftJoin('a.absenceJustificatif', 'j')
            ->where('a.scolariteSemestre IN (:scolariteSemesters)')
            ->andWhere('a.created >= :since')
            ->setParameter('scolariteSemesters', $scolariteSemesters)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $notifications = [];
        foreach ($absences as $absence) {
            $event = $absence->getEvent();
            $subjectName = trim(($event?->getCodeModule() ?? '').' '.($event?->getLibModule() ?? ''));
            $classSession = $event?->getDebut();
            $justification = match ($absence->getAbsenceJustificatif()?->getEtat()) {
                EtatJustificatifEnum::VALIDE => 'justificatif accepté',
                EtatJustificatifEnum::REFUSE => 'justificatif refusé',
                EtatJustificatifEnum::EN_ATTENTE => 'justificatif en cours d\'examen',
                default => 'aucun justificatif déposé',
            };

            $notifications[] = new Notification(
                'absence-'.$absence->getId(),
                Notification::TYPE_ABSENCE,
                '' === $subjectName ? 'Absence enregistrée' : 'Absence enregistrée en '.$subjectName,
                null === $classSession
                    ? ucfirst($justification)
                    : sprintf('Cours du %s à %s, %s', $classSession->format('d/m'), $classSession->format('H\hi'), $justification),
                \DateTimeImmutable::createFromInterface($absence->getCreated() ?? new \DateTimeImmutable()),
                '/intranet/scolarite',
            );
        }

        return $notifications;
    }
}
