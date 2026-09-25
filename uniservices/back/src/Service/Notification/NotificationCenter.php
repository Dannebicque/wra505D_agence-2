<?php

namespace App\Service\Notification;

use App\Entity\Users\Etudiant;
use App\Repository\Notification\ReadNotificationRepository;
use App\Service\Notification\Source\NotificationSourceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Réunit en un seul fil ce qui concerne l'étudiant : notes, absences, documents, actualités et
 * messages, du plus récent au plus ancien.
 */
final readonly class NotificationCenter
{
    /** Au-delà, un événement n'a plus rien de nouveau. */
    public const int RETENTION_DAYS = 90;

    /**
     * @param iterable<NotificationSourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator(NotificationSourceInterface::TAG)]
        private readonly iterable $sources,
        private readonly ReadNotificationRepository $readNotifications,
    ) {
    }

    /**
     * @return list<array{notification: Notification, lue: bool}>
     */
    public function forStudent(Etudiant $student, ?\DateTimeImmutable $now = null): array
    {
        $notifications = $this->collectNotifications($student, $now);
        $read = array_flip($this->readNotifications->readKeys($student, array_map(fn (Notification $notification) => $notification->key, $notifications)));

        return array_map(
            fn (Notification $notification) => ['notification' => $notification, 'lue' => isset($read[$notification->key])],
            $notifications,
        );
    }

    /**
     * Marque ces notifications comme lues, ou toutes celles du fil si aucune clé n'est donnée. Une
     * clé qui n'appartient pas au fil de l'étudiant est ignorée.
     *
     * @param list<string>|null $keys
     */
    public function markAsRead(Etudiant $student, ?array $keys = null, ?\DateTimeImmutable $now = null): void
    {
        $feedKeys = array_map(fn (Notification $notification) => $notification->key, $this->collectNotifications($student, $now));
        $keysToMark = null === $keys ? $feedKeys : array_values(array_intersect($feedKeys, $keys));

        $this->readNotifications->mark($student, $keysToMark);
    }

    /**
     * @return list<Notification>
     */
    private function collectNotifications(Etudiant $student, ?\DateTimeImmutable $now): array
    {
        $since = ($now ?? new \DateTimeImmutable())->modify(sprintf('-%d days', self::RETENTION_DAYS));

        $notifications = [];
        foreach ($this->sources as $source) {
            foreach ($source->getNotifications($student, $since) as $notification) {
                if ($notification->date >= $since) {
                    $notifications[$notification->key] = $notification;
                }
            }
        }

        $notifications = array_values($notifications);
        usort($notifications, fn (Notification $a, Notification $b) => $b->date <=> $a->date);

        return $notifications;
    }
}
