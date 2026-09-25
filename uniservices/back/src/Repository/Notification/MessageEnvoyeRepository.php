<?php

namespace App\Repository\Notification;

use App\Entity\Notification\MessageEnvoye;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageEnvoye>
 */
class MessageEnvoyeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageEnvoye::class);
    }

    /**
     * Messages reçus à l'une de ces adresses depuis cette date, du plus récent au plus ancien.
     *
     * @param list<string> $adresses
     *
     * @return list<MessageEnvoye>
     */
    public function recusDepuis(array $adresses, \DateTimeImmutable $depuis): array
    {
        $adresses = array_values(array_unique(array_map('mb_strtolower', array_filter($adresses))));
        if ([] === $adresses) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->where('m.destinataire IN (:adresses)')
            ->andWhere('m.envoyeLe >= :depuis')
            ->setParameter('adresses', $adresses)
            ->setParameter('depuis', $depuis)
            ->orderBy('m.envoyeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Enregistre la copie d'un envoi sans passer par l'unité de travail : l'envoi peut survenir au
     * milieu d'une autre opération, qu'un flush validerait trop tôt.
     */
    public function enregistrer(MessageEnvoye $message): void
    {
        $this->getEntityManager()->getConnection()->insert('message_envoye', [
            'destinataire' => $message->getDestinataire(),
            'sujet' => mb_substr($message->getSujet(), 0, 255),
            'texte' => $message->getTexte(),
            'envoye_le' => $message->getEnvoyeLe()->format('Y-m-d H:i:s'),
        ]);
    }
}
