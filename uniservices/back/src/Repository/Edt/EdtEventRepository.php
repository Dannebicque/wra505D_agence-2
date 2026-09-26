<?php

namespace App\Repository\Edt;

use App\Entity\Edt\EdtEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EdtEvent>
 */
class EdtEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EdtEvent::class);
    }

    /**
     * Récupère les événements d'EDT pour les statistiques, filtrés par semestre et année universitaire.
     * Les deux filtres sont optionnels: si null, ils ne sont pas appliqués.
     *
     * @return list<EdtEvent>
     */
    public function findForStatsBySemestreAndAnneeUniversitaire(?int $semestreId, ?int $anneeUniversitaireId): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.enseignement', 'ens')
            ->leftJoin('e.semestre', 'sem')
            ->leftJoin('e.anneeUniversitaire', 'au')
            ->leftJoin('e.personnel', 'p')
            ->addSelect('ens')
            ->addSelect('sem')
            ->addSelect('au')
            ->addSelect('p')
            // trier par nom puis prénom du personnel afin de faciliter un ordre stable côté fournisseur
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.prenom', 'ASC');

        if ($anneeUniversitaireId) {
            $qb->andWhere('au.id = :auId')->setParameter('auId', $anneeUniversitaireId);
        }
        if ($semestreId) {
            $qb->andWhere('sem.id = :semId')->setParameter('semId', $semestreId);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<EdtEvent> */
    public function findForStatsByAnneeAndAnneeUniversitaire(?int $annee, ?int $anneeUniversitaireId): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.enseignement', 'ens')
            ->leftJoin('e.semestre', 'sem')
            ->leftJoin('e.anneeUniversitaire', 'au')
            ->leftJoin('e.personnel', 'p')
            ->addSelect('ens')
            ->addSelect('sem')
            ->addSelect('au')
            ->addSelect('p')
            // trier par nom puis prénom du personnel afin de faciliter un ordre stable côté fournisseur
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.prenom', 'ASC');

        if ($anneeUniversitaireId) {
            $qb->andWhere('au.id = :auId')->setParameter('auId', $anneeUniversitaireId);
        }
        if ($annee) {
            $qb->andWhere('sem.annee = :annee')->setParameter('annee', $annee);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Créneaux de ces groupes qui commencent dans l'intervalle, comme le filtre groupe et day de
     * l'API que lit le widget « Maintenant » : les deux widgets ne peuvent pas se contredire.
     *
     * @param list<int> $groupeIds
     *
     * @return list<EdtEvent>
     */
    public function findByGroupesAndRange(array $groupeIds, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        if ([] === $groupeIds) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->join('e.groupe', 'g')
            ->andWhere('g.id IN (:groupes)')
            ->andWhere('e.debut >= :start')
            ->andWhere('e.debut < :end')
            ->setParameter('groupes', $groupeIds)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('e.debut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<EdtEvent> */
    public function findByPersonnelAndRange(int $personnelId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.personnel', 'p')
            ->andWhere('p.id = :personnel')
            ->andWhere('e.debut >= :start')
            ->andWhere('e.debut < :end')
            ->setParameter('personnel', $personnelId)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('e.debut', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
