<?php

namespace IntranetBundle\Commission\Service;

use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Structure\StructureSemestre;
use App\Utils\LooseValue;
use App\Repository\EtudiantScolariteSemestreRepository;
use IntranetBundle\Commission\Contract\CommissionDataLoaderInterface;
use IntranetBundle\Commission\Dto\CommissionDataset;
use IntranetBundle\Repository\Etudiant\MoyenneEnseignementRepository;
use IntranetBundle\Repository\Etudiant\MoyenneUeRepository;

final readonly class CommissionDataLoader implements CommissionDataLoaderInterface
{
    public function __construct(
        private EtudiantScolariteSemestreRepository $scolariteSemestreRepository,
        private MoyenneUeRepository $moyenneUeRepository,
        private MoyenneEnseignementRepository $moyenneEnseignementRepository,
    ) {
    }

    public function loadDataset(StructureSemestre $semestre): CommissionDataset
    {
        $scolarites = $this->scolariteSemestreRepository->createQueryBuilder('ess')
            ->leftJoin('ess.scolarite', 'scol')
            ->leftJoin('scol.etudiant', 'e')
            ->leftJoin('e.bac', 'b')
            ->addSelect('scol', 'e', 'b')
            ->where('ess.semestre = :semestre')
            ->setParameter('semestre', $semestre)
            ->getQuery()
            ->getResult();

        $moyennesUe = $this->normalizeIndexed((new \ReflectionMethod($this->moyenneUeRepository, 'findIndexedBySemestre'))->invoke($this->moyenneUeRepository, $semestre));
        $moyennesEnseignement = $this->normalizeIndexed((new \ReflectionMethod($this->moyenneEnseignementRepository, 'findIndexedBySemestre'))->invoke($this->moyenneEnseignementRepository, $semestre));

        return new CommissionDataset(
            $semestre,
            $scolarites,
            $moyennesUe,
            $moyennesEnseignement,
        );
    }

    /** @return array<int, array<int, float>> */
    private function normalizeIndexed(mixed $value): array
    {
        $result = [];
        foreach (LooseValue::rows($value) as $scolariteId => $rows) {
            foreach (LooseValue::row($rows) as $itemId => $moyenne) {
                $result[LooseValue::castInt($scolariteId)][LooseValue::castInt($itemId)] = LooseValue::castFloat($moyenne);
            }
        }

        return $result;
    }
}
