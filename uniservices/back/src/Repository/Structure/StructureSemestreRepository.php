<?php

namespace App\Repository\Structure;

use App\Entity\Structure\StructureSemestre;
use App\Repository\Traits\FindAllByIdArrayTrait;
use App\Repository\Traits\FindAllByOldIdArrayTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StructureSemestre>
 */
class StructureSemestreRepository extends ServiceEntityRepository
{
    use FindAllByIdArrayTrait;
    use FindAllByOldIdArrayTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StructureSemestre::class);
    }

    /**
     * @return StructureSemestre[]
     */
    public function findSemestresByDepartement(int $departementId): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.annee', 'a')
            ->join('a.pn', 'p')
            ->join('p.diplome', 'd')
            ->where('d.departement = :departementId')
            ->setParameter('departementId', $departementId)
            ->getQuery()
            ->getResult();
    }
}
