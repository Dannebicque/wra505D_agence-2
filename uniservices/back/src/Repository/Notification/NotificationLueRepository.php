<?php

namespace App\Repository\Notification;

use App\Entity\Notification\NotificationLue;
use App\Entity\Users\Etudiant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationLue>
 */
class NotificationLueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationLue::class);
    }

    /**
     * Parmi ces clés, celles que l'étudiant a déjà lues.
     *
     * @param list<string> $cles
     *
     * @return list<string>
     */
    public function clesLues(Etudiant $etudiant, array $cles): array
    {
        if ([] === $cles) {
            return [];
        }

        return $this->createQueryBuilder('l')
            ->select('l.cle')
            ->where('l.etudiant = :etudiant')
            ->andWhere('l.cle IN (:cles)')
            ->setParameter('etudiant', $etudiant)
            ->setParameter('cles', $cles)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Enregistre la lecture de ces clés, sans doublon.
     *
     * @param list<string> $cles
     */
    public function marquer(Etudiant $etudiant, array $cles): void
    {
        $dejaLues = array_flip($this->clesLues($etudiant, $cles));
        foreach (array_unique($cles) as $cle) {
            if (!isset($dejaLues[$cle])) {
                $this->getEntityManager()->persist(new NotificationLue($etudiant, $cle));
            }
        }

        $this->getEntityManager()->flush();
    }
}
