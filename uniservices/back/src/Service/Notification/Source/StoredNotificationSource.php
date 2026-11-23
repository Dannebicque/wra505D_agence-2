<?php

namespace App\Service\Notification\Source;

use App\Entity\Notification\Notification as StoredNotification;
use App\Entity\Users\Etudiant;
use App\Notification\NotificationTypeRegistry;
use App\Service\Notification\Notification;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les notifications que les modules enregistrent pour l'étudiant (App\Notification\NotificationService). Elles
 * rejoignent son fil unique : l'étudiant n'a pas la cloche du personnel.
 */
final class StoredNotificationSource implements NotificationSourceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationTypeRegistry $types,
    ) {
    }

    public function getNotifications(Etudiant $student, \DateTimeImmutable $since): iterable
    {
        /** @var list<StoredNotification> $stored */
        $stored = $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(StoredNotification::class, 'n')
            ->where('n.etudiant = :student')
            ->andWhere('n.misAJourLe >= :since')
            ->setParameter('student', $student)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $notifications = [];
        foreach ($stored as $notification) {
            $notifications[] = self::toFeedItem($notification, $this->types->get($notification->getType())?->libelle);
        }

        return $notifications;
    }

    /**
     * Le libellé du type sert de titre ; sans type connu, le texte en tient lieu.
     */
    public static function toFeedItem(StoredNotification $notification, ?string $typeLabel): Notification
    {
        return new Notification(
            'module-'.$notification->getUuidString(),
            Notification::TYPE_MESSAGE,
            $typeLabel ?? $notification->getTexte(),
            $notification->getTexte(),
            $notification->getMisAJourLe(),
            $notification->getUrl() ?? '/intranet/notifications',
        );
    }
}
