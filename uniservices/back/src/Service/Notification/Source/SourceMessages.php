<?php

namespace App\Service\Notification\Source;

use App\Entity\Users\Etudiant;
use App\Repository\Notification\MessageEnvoyeRepository;
use App\Service\Notification\Notification;

/**
 * Un e-mail que l'intranet a envoyé à l'étudiant, sur son adresse universitaire ou personnelle.
 */
final class SourceMessages implements SourceNotificationInterface
{
    public function __construct(
        private readonly MessageEnvoyeRepository $messages,
    ) {
    }

    public function notifications(Etudiant $etudiant, \DateTimeImmutable $depuis): iterable
    {
        $notifications = [];
        foreach ($this->messages->recusDepuis(array_filter([$etudiant->getMailUniv(), $etudiant->getMailPerso()]), $depuis) as $message) {
            $notifications[] = new Notification(
                'message-'.$message->getId(),
                Notification::TYPE_MESSAGE,
                $message->getSujet(),
                $message->getTexte(),
                $message->getEnvoyeLe(),
                '/intranet/notifications',
            );
        }

        return $notifications;
    }
}
