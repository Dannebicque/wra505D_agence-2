<?php

namespace App\Repository\Structure;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Repository\Traits\FindAllByIdArrayTrait;
use App\Repository\Traits\FindAllByOldIdArrayTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StructureDepartement>
 */
class StructureDepartementRepository extends ServiceEntityRepository
{
    use FindAllByIdArrayTrait;
    use FindAllByOldIdArrayTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StructureDepartement::class);
    }

    public function findOneByEtudiant(Etudiant $etudiant): ?StructureDepartement
    {
        // récupérer le département de etudiant.etudiantScolarite quand etudiantScolarite.actif = true
        $qb = $this->createQueryBuilder('d')
            ->innerJoin('d.scolarites', 'es')
            ->where('es.etudiant = :etudiant')
            ->andWhere('es.actif = true')
            ->setParameter('etudiant', $etudiant)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
