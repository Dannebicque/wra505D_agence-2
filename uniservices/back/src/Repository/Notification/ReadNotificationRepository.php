<?php

namespace App\Repository\Notification;

use App\Entity\Notification\ReadNotification;
use App\Entity\Users\Etudiant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadNotification>
 */
class ReadNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadNotification::class);
    }

    /**
     * Parmi ces clés, celles que l'étudiant a déjà lues.
     *
     * @param list<string> $keys
     *
     * @return list<string>
     */
    public function readKeys(Etudiant $student, array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        return $this->createQueryBuilder('l')
            ->select('l.key')
            ->where('l.student = :student')
            ->andWhere('l.key IN (:keys)')
            ->setParameter('student', $student)
            ->setParameter('keys', $keys)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Enregistre la lecture de ces clés, sans doublon.
     *
     * @param list<string> $keys
     */
    public function mark(Etudiant $student, array $keys): void
    {
        $alreadyRead = array_flip($this->readKeys($student, $keys));
        foreach (array_unique($keys) as $key) {
            if (!isset($alreadyRead[$key])) {
                $this->getEntityManager()->persist(new ReadNotification($student, $key));
            }
        }

        $this->getEntityManager()->flush();
    }
}
