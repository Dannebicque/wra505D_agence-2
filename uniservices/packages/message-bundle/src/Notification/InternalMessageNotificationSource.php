<?php

namespace MessageBundle\Notification;

use App\Entity\Users\Etudiant;
use App\Service\Notification\Notification;
use App\Service\Notification\Source\NotificationSourceInterface;
use MessageBundle\Repository\MessageDestinataireRepository;

/**
 * Un message de la messagerie interne reçu par l'étudiant. Il n'a pas d'écran de messagerie : ses
 * messages arrivent dans son fil de notifications.
 */
final class InternalMessageNotificationSource implements NotificationSourceInterface
{
    public function __construct(
        private readonly MessageDestinataireRepository $destinataires,
    ) {
    }

    public function getNotifications(Etudiant $student, \DateTimeImmutable $since): iterable
    {
        $notifications = [];
        foreach ($this->destinataires->findReceivedByStudentSince($student, $since) as $destinataire) {
            $message = $destinataire->getMessage();
            $date = $message?->getCreatedAt();
            if ($message === null || $date === null) {
                continue;
            }
            $notifications[] = new Notification(
                'messagerie-'.$message->getId(),
                Notification::TYPE_MESSAGE,
                (string) $message->getSujet(),
                trim(html_entity_decode(strip_tags((string) $message->getContenu()))),
                $date,
                '/intranet/notifications',
            );
        }

        return $notifications;
    }
}
