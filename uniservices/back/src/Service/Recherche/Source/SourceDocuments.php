<?php

namespace App\Service\Recherche\Source;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Recherche\Candidat;
use Doctrine\ORM\EntityManagerInterface;
use DocumentBundle\Entity\Document;

/**
 * Documents du département, ou communs à tous, que l'utilisateur a le droit de voir.
 */
final class SourceDocuments implements SourceRechercheInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Valeurs de Document::$visibility accessibles à ce type d'utilisateur.
     * DEPARTEMENT vise les membres du département, étudiants comme personnels.
     *
     * @return list<string>
     */
    public static function visibilitesPour(Etudiant|Personnel $utilisateur): array
    {
        return $utilisateur instanceof Etudiant
            ? ['PUBLIC', 'ETUDIANT', 'DEPARTEMENT']
            : ['PUBLIC', 'PERSONNEL', 'DEPARTEMENT'];
    }

    public function candidats(StructureDepartement $departement, Etudiant|Personnel $utilisateur): iterable
    {
        $documents = $this->entityManager->createQueryBuilder()
            ->select('d.id', 'd.titre', 'c.libelle AS categorie')
            ->from(Document::class, 'd')
            ->leftJoin('d.category', 'c')
            ->where('d.departement = :departement OR d.departement IS NULL')
            ->andWhere('d.visibility IN (:visibilites)')
            ->setParameter('departement', $departement)
            ->setParameter('visibilites', self::visibilitesPour($utilisateur))
            ->getQuery()
            ->getArrayResult();

        foreach ($documents as $document) {
            if (null === $document['titre']) {
                continue;
            }

            yield new Candidat('document', $document['id'], $document['titre'], $document['categorie'], $document['titre']);
        }
    }
}
