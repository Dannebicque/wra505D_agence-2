<?php

namespace App\Service\Notification\Source;

use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Users\Etudiant;
use App\Service\Notification\Notification;
use App\Service\Notification\StudentSemesters;
use Doctrine\ORM\EntityManagerInterface;
use DocumentBundle\Entity\Document;

/**
 * Un document ajouté dans l'une des matières ou SAÉ de ses semestres, qu'il a le droit de voir.
 */
final class DocumentNotificationSource implements NotificationSourceInterface
{
    /** Valeurs de Document::$visibility ouvertes aux étudiants, comme dans la recherche. */
    private const VISIBILITIES = ['PUBLIC', 'ETUDIANT'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StudentSemesters $semesters,
    ) {
    }

    public function getNotifications(Etudiant $student, \DateTimeImmutable $since): iterable
    {
        $semesters = array_map(
            fn (EtudiantScolariteSemestre $scolariteSemestre) => $scolariteSemestre->getSemestre(),
            $this->semesters->forStudent($student),
        );
        if ([] === $semesters) {
            return [];
        }

        /** @var list<Document> $documents */
        $documents = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT d', 'ens')
            ->from(Document::class, 'd')
            ->join('d.enseignement', 'ens')
            ->join('ens.enseignementUes', 'eu')
            ->join('eu.ue', 'ue')
            ->where('ue.semestre IN (:semesters)')
            ->andWhere('d.visibility IN (:visibilites)')
            ->andWhere('d.createdAt >= :since')
            ->setParameter('semesters', $semesters)
            ->setParameter('visibilites', self::VISIBILITIES)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $notifications = [];
        foreach ($documents as $document) {
            $enseignement = $document->getEnseignement();
            $notifications[] = new Notification(
                'document-'.$document->getId(),
                Notification::TYPE_DOCUMENT,
                sprintf('Nouveau document en %s %s', $enseignement?->getCodeEnseignement(), $enseignement?->getLibelle()),
                $document->getTitre(),
                $document->getCreatedAt(),
                '/documents',
            );
        }

        return $notifications;
    }
}
