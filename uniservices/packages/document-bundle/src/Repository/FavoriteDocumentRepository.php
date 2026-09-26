<?php

declare(strict_types=1);

namespace DocumentBundle\Repository;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Entity\Document;
use DocumentBundle\Entity\FavoriteDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteDocument>
 */
class FavoriteDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteDocument::class);
    }

    /**
     * Identifiants des documents que cet utilisateur a mis en favori.
     *
     * @return list<int>
     */
    public function documentIds(Etudiant|Personnel $user): array
    {
        return array_map('intval', $this->forUser($user)
            ->select('IDENTITY(f.document)')
            ->getQuery()
            ->getSingleColumnResult());
    }

    public function countFor(Etudiant|Personnel $user): int
    {
        return (int) $this->forUser($user)
            ->select('COUNT(f.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFor(Etudiant|Personnel $user, Document $document): ?FavoriteDocument
    {
        return $this->forUser($user)
            ->andWhere('f.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function forUser(Etudiant|Personnel $user): QueryBuilder
    {
        return $this->createQueryBuilder('f')
            ->where($user instanceof Etudiant ? 'f.student = :utilisateur' : 'f.staff = :utilisateur')
            ->setParameter('utilisateur', $user);
    }
}
