<?php

namespace IntranetBundle\Repository\Etudiant;

use App\Entity\Structure\StructureSemestre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use IntranetBundle\Entity\Etudiant\MoyenneUe;

/**
 * @extends ServiceEntityRepository<MoyenneUe>
 */
class MoyenneUeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoyenneUe::class);
    }

    /**
     * @return array<int, array<int, float>> [scolariteSemestreId][ueId] => moyenne
     */
    public function findIndexedBySemestre(StructureSemestre $semestre): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.etudiantScolariteSemestre) AS scolId', 'IDENTITY(m.ue) AS ueId', 'm.value AS value')
            ->join('m.etudiantScolariteSemestre', 'ess')
            ->where('ess.semestre = :semestre')
            ->setParameter('semestre', $semestre)
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $normalizedRow = self::normalizeRow($row);
            if (null === $normalizedRow) {
                continue;
            }
            $result[$normalizedRow['scolId']][$normalizedRow['ueId']] = $normalizedRow['value'];
        }

        return $result;
    }

    /**
     * @return array{scolId: int, ueId: int, value: float}|null
     */
    private static function normalizeRow(mixed $row): ?array
    {
        if (
            !is_array($row)
            || !array_key_exists('scolId', $row)
            || !array_key_exists('ueId', $row)
            || !array_key_exists('value', $row)
            || !is_numeric($row['scolId'])
            || !is_numeric($row['ueId'])
            || !is_numeric($row['value'])
        ) {
            return null;
        }

        return [
            'scolId' => intval($row['scolId']),
            'ueId' => intval($row['ueId']),
            'value' => floatval($row['value']),
        ];
    }

    //    /**
    //     * @return MoyenneUe[] Returns an array of MoyenneUe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?MoyenneUe
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
