<?php

namespace App\Service\Notification\Source;

use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Users\Etudiant;
use App\Service\Notification\Notification;
use App\Service\Notification\SemestresEtudiant;
use Doctrine\ORM\EntityManagerInterface;
use DocumentBundle\Entity\Document;

/**
 * Un document ajouté dans l'une des matières ou SAÉ de ses semestres, qu'il a le droit de voir.
 */
final class SourceDocuments implements SourceNotificationInterface
{
    /** Valeurs de Document::$visibility ouvertes aux étudiants, comme dans la recherche. */
    private const VISIBILITES = ['PUBLIC', 'ETUDIANT'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SemestresEtudiant $semestres,
    ) {
    }

    public function notifications(Etudiant $etudiant, \DateTimeImmutable $depuis): iterable
    {
        $semestres = array_map(
            fn (EtudiantScolariteSemestre $scolariteSemestre) => $scolariteSemestre->getSemestre(),
            $this->semestres->pour($etudiant),
        );
        if ([] === $semestres) {
            return [];
        }

        /** @var list<Document> $documents */
        $documents = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT d', 'ens')
            ->from(Document::class, 'd')
            ->join('d.enseignement', 'ens')
            ->join('ens.enseignementUes', 'eu')
            ->join('eu.ue', 'ue')
            ->where('ue.semestre IN (:semestres)')
            ->andWhere('d.visibility IN (:visibilites)')
            ->andWhere('d.createdAt >= :depuis')
            ->setParameter('semestres', $semestres)
            ->setParameter('visibilites', self::VISIBILITES)
            ->setParameter('depuis', $depuis)
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
