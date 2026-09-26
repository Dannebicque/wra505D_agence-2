<?php

namespace App\Service\Notification\Source;

use App\Entity\Users\Etudiant;
use App\Repository\Notification\SentMessageRepository;
use App\Service\Notification\Notification;

/**
 * Un e-mail que l'intranet a envoyé à l'étudiant, sur son adresse universitaire ou personnelle.
 */
final class MessageNotificationSource implements NotificationSourceInterface
{
    public function __construct(
        private readonly SentMessageRepository $messages,
    ) {
    }

    public function getNotifications(Etudiant $student, \DateTimeImmutable $since): iterable
    {
        $notifications = [];
        $addresses = array_values(array_filter([$student->getMailUniv(), $student->getMailPerso()]));
        foreach ($this->messages->receivedSince($addresses, $since) as $message) {
            $notifications[] = new Notification(
                'message-'.$message->getId(),
                Notification::TYPE_MESSAGE,
                $message->getSubject(),
                $message->getText(),
                $message->getSentAt(),
                '/intranet/notifications',
            );
        }

        return $notifications;
    }
}
