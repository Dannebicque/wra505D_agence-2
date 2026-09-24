<?php

namespace App\Service\Recherche\Source;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Recherche\Candidat;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Étudiants inscrits cette année dans le département, comme dans la recherche de l'intranet V3.
 */
final class SourceEtudiants implements SourceRechercheInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function candidats(StructureDepartement $departement, Etudiant|Personnel $utilisateur): iterable
    {
        $lignes = $this->entityManager->createQueryBuilder()
            ->select('e.id', 'e.nom', 'e.prenom', 'e.username', 'e.mailUniv', 'sem.libelle AS semestre')
            ->from(EtudiantScolarite::class, 's')
            ->join('s.etudiant', 'e')
            ->leftJoin('s.scolariteSemestre', 'ss')
            ->leftJoin('ss.semestre', 'sem')
            ->where('s.departement = :departement')
            ->andWhere('s.actif = true')
            ->setParameter('departement', $departement)
            ->getQuery()
            ->getArrayResult();

        // Une ligne par semestre : on regroupe par étudiant.
        $etudiants = [];
        foreach ($lignes as $ligne) {
            $etudiants[$ligne['id']] ??= $ligne + ['semestres' => []];
            if (null !== $ligne['semestre']) {
                $etudiants[$ligne['id']]['semestres'][] = $ligne['semestre'];
            }
        }

        foreach ($etudiants as $etudiant) {
            yield new Candidat(
                'etudiant',
                $etudiant['id'],
                $etudiant['prenom'].' '.$etudiant['nom'],
                [] === $etudiant['semestres'] ? null : implode(', ', array_unique($etudiant['semestres'])),
                $etudiant['prenom'].' '.$etudiant['nom'].' '.$etudiant['username'],
                $etudiant['mailUniv'],
            );
        }
    }
}
