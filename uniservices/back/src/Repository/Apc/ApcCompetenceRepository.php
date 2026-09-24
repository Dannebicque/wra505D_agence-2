<?php

namespace App\Repository\Apc;

use App\Entity\Apc\ApcCompetence;
use App\Entity\Structure\StructureDiplome;
use App\Repository\Traits\FindAllByIdArrayTrait;
use App\Repository\Traits\FindAllByOldIdArrayTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApcCompetence>
 */
class ApcCompetenceRepository extends ServiceEntityRepository
{
    use FindAllByIdArrayTrait;
    use FindAllByOldIdArrayTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApcCompetence::class);
    }

    /**
     * @return ApcCompetence[]
     */
    public function findByDiplome(StructureDiplome $diplome): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.referentiel', 'r')
            ->andWhere('r = :referentiel')
            ->setParameter('referentiel', $diplome->getReferentiel())
            ->getQuery()
            ->getResult();
    }
}
