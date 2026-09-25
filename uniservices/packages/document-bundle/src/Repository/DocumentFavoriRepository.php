<?php

namespace DocumentBundle\Repository;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Entity\Document;
use DocumentBundle\Entity\DocumentFavori;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentFavori>
 */
class DocumentFavoriRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentFavori::class);
    }

    /**
     * Identifiants des documents que cet utilisateur a mis en favori.
     *
     * @return list<int>
     */
    public function idsDocuments(Etudiant|Personnel $utilisateur): array
    {
        return array_map('intval', $this->pour($utilisateur)
            ->select('IDENTITY(f.document)')
            ->getQuery()
            ->getSingleColumnResult());
    }

    public function compter(Etudiant|Personnel $utilisateur): int
    {
        return (int) $this->pour($utilisateur)
            ->select('COUNT(f.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function trouver(Etudiant|Personnel $utilisateur, Document $document): ?DocumentFavori
    {
        return $this->pour($utilisateur)
            ->andWhere('f.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function pour(Etudiant|Personnel $utilisateur): QueryBuilder
    {
        return $this->createQueryBuilder('f')
            ->where($utilisateur instanceof Etudiant ? 'f.etudiant = :utilisateur' : 'f.personnel = :utilisateur')
            ->setParameter('utilisateur', $utilisateur);
    }
}
