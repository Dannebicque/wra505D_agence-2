<?php

namespace App\Repository\Structure;

use App\Entity\Structure\StructureGroupe;
use App\Repository\Traits\FindAllByIdArrayTrait;
use App\Repository\Traits\FindAllByOldIdArrayTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StructureGroupe>
 */
class StructureGroupeRepository extends ServiceEntityRepository
{
    use FindAllByIdArrayTrait;
    use FindAllByOldIdArrayTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StructureGroupe::class);
    }

    /**
     * @return StructureGroupe[]
     */
    public function findBySemestreId(int|string $semestreId): array
    {
        return $this->createQueryBuilder('g')
            ->join('g.semestres', 's')
            ->andWhere('s.id = :semestreId')
            ->andWhere('g.parent IS NULL')
            ->setParameter('semestreId', $semestreId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les groupes du semestre, groupes enfants compris.
     *
     * @return list<StructureGroupe>
     */
    public function findAllBySemestreId(int $semestreId): array
    {
        /** @var list<StructureGroupe> */
        return $this->createQueryBuilder('g')
            ->join('g.semestres', 's')
            ->andWhere('s.id = :semestreId')
            ->setParameter('semestreId', $semestreId)
            ->getQuery()
            ->getResult();
    }
}
