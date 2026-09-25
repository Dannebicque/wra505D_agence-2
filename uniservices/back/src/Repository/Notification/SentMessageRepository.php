<?php

namespace App\Repository\Notification;

use App\Entity\Notification\SentMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SentMessage>
 */
class SentMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SentMessage::class);
    }

    /**
     * Messages reçus à l'une de ces adresses depuis cette date, du plus récent au plus ancien.
     *
     * @param list<string> $addresses
     *
     * @return list<SentMessage>
     */
    public function receivedSince(array $addresses, \DateTimeImmutable $since): array
    {
        $addresses = array_values(array_unique(array_map('mb_strtolower', array_filter($addresses))));
        if ([] === $addresses) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->where('m.recipient IN (:addresses)')
            ->andWhere('m.sentAt >= :since')
            ->setParameter('addresses', $addresses)
            ->setParameter('since', $since)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Enregistre la copie d'un envoi sans passer par l'unité de travail : l'envoi peut survenir au
     * milieu d'une autre opération, qu'un flush validerait trop tôt.
     */
    public function save(SentMessage $message): void
    {
        $this->getEntityManager()->getConnection()->insert('message_envoye', [
            'destinataire' => $message->getRecipient(),
            'subject' => mb_substr($message->getSubject(), 0, 255),
            'text' => $message->getText(),
            'envoye_le' => $message->getSentAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
