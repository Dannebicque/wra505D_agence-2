<?php

namespace App\Service\Notification\Source;

use App\Entity\Users\Etudiant;
use App\Service\Notification\Notification;
use App\Service\Notification\SemestresEtudiant;
use Doctrine\ORM\EntityManagerInterface;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use IntranetBundle\Enum\EtatJustificatifEnum;

/**
 * Une absence enregistrée, avec où en est son justificatif. Le justificatif ne porte pas de date
 * de décision : son état figure dans le texte, sans notification à part.
 */
final class SourceAbsences implements SourceNotificationInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SemestresEtudiant $semestres,
    ) {
    }

    public function notifications(Etudiant $etudiant, \DateTimeImmutable $depuis): iterable
    {
        $scolariteSemestres = $this->semestres->pour($etudiant);
        if ([] === $scolariteSemestres) {
            return [];
        }

        /** @var list<EtudiantAbsence> $absences */
        $absences = $this->entityManager->createQueryBuilder()
            ->select('a', 'ev', 'j')
            ->from(EtudiantAbsence::class, 'a')
            ->leftJoin('a.event', 'ev')
            ->leftJoin('a.absenceJustificatif', 'j')
            ->where('a.scolariteSemestre IN (:scolariteSemestres)')
            ->andWhere('a.created >= :depuis')
            ->setParameter('scolariteSemestres', $scolariteSemestres)
            ->setParameter('depuis', $depuis)
            ->getQuery()
            ->getResult();

        $notifications = [];
        foreach ($absences as $absence) {
            $event = $absence->getEvent();
            $matiere = trim(($event?->getCodeModule() ?? '').' '.($event?->getLibModule() ?? ''));
            $cours = $event?->getDebut();
            $justificatif = match ($absence->getAbsenceJustificatif()?->getEtat()) {
                EtatJustificatifEnum::VALIDE => 'justificatif accepté',
                EtatJustificatifEnum::REFUSE => 'justificatif refusé',
                EtatJustificatifEnum::EN_ATTENTE => 'justificatif en cours d\'examen',
                default => 'aucun justificatif déposé',
            };

            $notifications[] = new Notification(
                'absence-'.$absence->getId(),
                Notification::TYPE_ABSENCE,
                '' === $matiere ? 'Absence enregistrée' : 'Absence enregistrée en '.$matiere,
                null === $cours
                    ? ucfirst($justificatif)
                    : sprintf('Cours du %s à %s, %s', $cours->format('d/m'), $cours->format('H\hi'), $justificatif),
                \DateTimeImmutable::createFromInterface($absence->getCreated() ?? new \DateTimeImmutable()),
                '/intranet/scolarite',
            );
        }

        return $notifications;
    }
}
