<?php

declare(strict_types=1);

namespace App\Service\Search\Source;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\StatutEnum;
use App\Service\Search\Candidate;
use Doctrine\ORM\EntityManagerInterface;
use App\Utils\LooseValue;

/**
 * Personnels rattachés au département, avec leur statut pour les distinguer.
 */
final readonly class StaffSearchSource implements SearchSourceInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findCandidates(StructureDepartement $department, Etudiant|Personnel $user): iterable
    {
        $staffMembers = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT p.id', 'p.nom', 'p.prenom', 'p.username', 'p.mailUniv', 'p.statut')
            ->from(StructureDepartementPersonnel::class, 'dp')
            ->join('dp.personnel', 'p')
            ->where('dp.departement = :departement')
            ->setParameter('departement', $department)
            ->getQuery()
            ->getArrayResult();

        foreach (LooseValue::rows($staffMembers) as $staffMember) {
            yield new Candidate(
                'personnel',
                LooseValue::int($staffMember['id']),
                LooseValue::castString($staffMember['prenom']).' '.LooseValue::castString($staffMember['nom']),
                $staffMember['statut'] instanceof StatutEnum ? $staffMember['statut']->getLibelle() : null,
                LooseValue::castString($staffMember['prenom']).' '.LooseValue::castString($staffMember['nom']).' '.LooseValue::castString($staffMember['username']),
                LooseValue::nullableString($staffMember['mailUniv']),
            );
        }
    }
}
