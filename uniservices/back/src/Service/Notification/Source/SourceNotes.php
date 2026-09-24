<?php

namespace App\Service\Notification\Source;

use App\Entity\Etudiant\EtudiantNote;
use App\Entity\Users\Etudiant;
use App\Enum\EtatEvaluationEnum;
use App\Service\Notification\Notification;
use App\Service\Notification\SemestresEtudiant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Une note d'une évaluation publiée. Une note saisie mais pas publiée ne se signale pas.
 */
final class SourceNotes implements SourceNotificationInterface
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

        /** @var list<EtudiantNote> $notes */
        $notes = $this->entityManager->createQueryBuilder()
            ->select('n', 'e', 'ens')
            ->from(EtudiantNote::class, 'n')
            ->join('n.evaluation', 'e')
            ->leftJoin('e.enseignement', 'ens')
            ->where('n.scolariteSemestre IN (:scolariteSemestres)')
            ->andWhere('e.etat = :publiee')
            ->setParameter('scolariteSemestres', $scolariteSemestres)
            ->setParameter('publiee', EtatEvaluationEnum::ETAT_PUBLIEE)
            ->getQuery()
            ->getResult();

        $notifications = [];
        foreach ($notes as $note) {
            $date = self::derniereModification($note);
            if (null === $date || $date < $depuis) {
                continue;
            }

            $evaluation = $note->getEvaluation();
            $enseignement = $evaluation?->getEnseignement();
            $matiere = null === $enseignement ? null : trim($enseignement->getCodeEnseignement().' '.$enseignement->getLibelle());

            $notifications[] = new Notification(
                'note-'.$note->getId(),
                Notification::TYPE_NOTE,
                null === $matiere ? 'Nouvelle note' : 'Nouvelle note en '.$matiere,
                sprintf('%s : %s', $evaluation?->getLibelle() ?? 'Évaluation', self::resultat($note)),
                $date,
                '/intranet/scolarite',
            );
        }

        return $notifications;
    }

    /**
     * Même lecture que la page Notes et absences : le statut dit ce que vaut la note.
     */
    public static function resultat(EtudiantNote $note): string
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

    private static function derniereModification(EtudiantNote $note): ?\DateTimeImmutable
    {
        $date = $note->getUpdated() ?? $note->getCreated();

        return null === $date ? null : \DateTimeImmutable::createFromInterface($date);
    }
}
