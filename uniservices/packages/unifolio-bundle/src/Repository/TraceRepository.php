<?php

namespace UnifolioBundle\Repository;

use App\Entity\Users\Etudiant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use UnifolioBundle\Entity\Trace;
use UnifolioBundle\Enum\StatutTraceEnum;

/** @extends ServiceEntityRepository<Trace> */
class TraceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trace::class);
    }

    public function findOneByUuid(string $uuid): ?Trace
    {
        if (!Uuid::isValid($uuid)) {
            return null;
        }

        return $this->findOneBy(['uuid' => Uuid::fromString($uuid)]);
    }

    /**
     * Bibliothèque d'un étudiant, filtrable.
     *
     * @param array{scolarite?: ?int, statut?: ?StatutTraceEnum, competence?: ?int, q?: ?string} $filtres
     *
     * @return Trace[]
     */
    public function findBibliotheque(Etudiant $etudiant, array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.medias', 'm')->addSelect('m')
            ->leftJoin('t.rattachements', 'r')->addSelect('r')
            ->andWhere('t.etudiant = :etudiant')
            ->setParameter('etudiant', $etudiant)
            ->orderBy('t.dateRealisation', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if (($filtres['scolarite'] ?? null) !== null && $filtres['scolarite'] !== 0) {
            $qb->andWhere('IDENTITY(t.scolarite) = :scolarite')->setParameter('scolarite', $filtres['scolarite']);
        }
        if (($filtres['statut'] ?? null) instanceof StatutTraceEnum) {
            $qb->andWhere('t.statut = :statut')->setParameter('statut', $filtres['statut']);
        }
        if (($filtres['q'] ?? null) !== null && $filtres['q'] !== '') {
            $qb->andWhere('t.titre LIKE :q OR t.contexte LIKE :q')
                ->setParameter('q', '%' . addcslashes($filtres['q'], '%_') . '%');
        }
        if (($filtres['competence'] ?? null) !== null && $filtres['competence'] !== 0) {
            $sub = $this->getEntityManager()->createQueryBuilder()
                ->select('IDENTITY(rf.trace)')
                ->from(\UnifolioBundle\Entity\TraceRattachement::class, 'rf')
                ->leftJoin('rf.niveau', 'nf')
                ->leftJoin('rf.apprentissageCritique', 'af')
                ->leftJoin('af.niveau', 'anf')
                ->where('IDENTITY(rf.competence) = :competence OR IDENTITY(nf.competence) = :competence OR IDENTITY(anf.competence) = :competence');
            $qb->andWhere($qb->expr()->in('t.id', $sub->getDQL()))->setParameter('competence', $filtres['competence']);
        }

        return $qb->getQuery()->getResult();
    }
}
