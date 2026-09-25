<?php

declare(strict_types=1);

namespace App\Service\Search\Source;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Search\Candidate;
use Doctrine\ORM\EntityManagerInterface;
use DocumentBundle\Entity\Document;

/**
 * Documents du département, ou communs à tous, que l'utilisateur a le droit de voir.
 */
final readonly class DocumentSearchSource implements SearchSourceInterface
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
    public static function visibilityFor(Etudiant|Personnel $user): array
    {
        return $user instanceof Etudiant
            ? ['PUBLIC', 'ETUDIANT', 'DEPARTEMENT']
            : ['PUBLIC', 'PERSONNEL', 'DEPARTEMENT'];
    }

    public function findCandidates(StructureDepartement $department, Etudiant|Personnel $user): iterable
    {
        $documents = $this->entityManager->createQueryBuilder()
            ->select('d.id', 'd.titre', 'c.libelle AS categorie')
            ->from(Document::class, 'd')
            ->leftJoin('d.category', 'c')
            ->where('d.departement = :departement OR d.departement IS NULL')
            ->andWhere('d.visibility IN (:visibilites)')
            ->setParameter('departement', $department)
            ->setParameter('visibilites', self::visibilityFor($user))
            ->getQuery()
            ->getArrayResult();

        foreach ($documents as $document) {
            if (null === $document['titre']) {
                continue;
            }

            yield new Candidate('document', $document['id'], $document['titre'], $document['categorie'], $document['titre']);
        }
    }
}
