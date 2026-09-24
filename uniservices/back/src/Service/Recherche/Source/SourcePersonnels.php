<?php

namespace App\Service\Recherche\Source;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Recherche\Candidat;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Personnels rattachés au département.
 */
final class SourcePersonnels implements SourceRechercheInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function candidats(StructureDepartement $departement, Etudiant|Personnel $utilisateur): iterable
    {
        $personnels = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT p.id', 'p.nom', 'p.prenom', 'p.username', 'p.mailUniv')
            ->from(StructureDepartementPersonnel::class, 'dp')
            ->join('dp.personnel', 'p')
            ->where('dp.departement = :departement')
            ->setParameter('departement', $departement)
            ->getQuery()
            ->getArrayResult();

        foreach ($personnels as $personnel) {
            yield new Candidat(
                'personnel',
                $personnel['id'],
                $personnel['prenom'].' '.$personnel['nom'],
                null,
                $personnel['prenom'].' '.$personnel['nom'].' '.$personnel['username'],
                $personnel['mailUniv'],
            );
        }
    }
}
