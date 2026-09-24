<?php

namespace App\Service\Notification;

use App\Entity\Users\Etudiant;
use App\Repository\Notification\NotificationLueRepository;
use App\Service\Notification\Source\SourceNotificationInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Réunit en un seul fil ce qui concerne l'étudiant : notes, absences, documents, actualités et
 * messages, du plus récent au plus ancien.
 */
final class CentreNotifications
{
    /** Au-delà, un événement n'a plus rien de nouveau. */
    public const JOURS_CONSERVES = 90;

    /**
     * @param iterable<SourceNotificationInterface> $sources
     */
    public function __construct(
        #[AutowireIterator(SourceNotificationInterface::TAG)]
        private readonly iterable $sources,
        private readonly NotificationLueRepository $lectures,
    ) {
    }

    /**
     * @return list<array{notification: Notification, lue: bool}>
     */
    public function pour(Etudiant $etudiant, ?\DateTimeImmutable $maintenant = null): array
    {
        $notifications = $this->notifications($etudiant, $maintenant);
        $lues = array_flip($this->lectures->clesLues($etudiant, array_map(fn (Notification $n) => $n->cle, $notifications)));

        return array_map(
            fn (Notification $notification) => ['notification' => $notification, 'lue' => isset($lues[$notification->cle])],
            $notifications,
        );
    }

    /**
     * Marque ces notifications comme lues, ou toutes celles du fil si aucune clé n'est donnée. Une
     * clé qui n'appartient pas au fil de l'étudiant est ignorée.
     *
     * @param list<string>|null $cles
     */
    public function marquerLues(Etudiant $etudiant, ?array $cles = null, ?\DateTimeImmutable $maintenant = null): void
    {
        $clesDuFil = array_map(fn (Notification $n) => $n->cle, $this->notifications($etudiant, $maintenant));
        $aMarquer = null === $cles ? $clesDuFil : array_values(array_intersect($clesDuFil, $cles));

        $this->lectures->marquer($etudiant, $aMarquer);
    }

    /**
     * @return list<Notification>
     */
    private function notifications(Etudiant $etudiant, ?\DateTimeImmutable $maintenant): array
    {
        $depuis = ($maintenant ?? new \DateTimeImmutable())->modify(sprintf('-%d days', self::JOURS_CONSERVES));

        $notifications = [];
        foreach ($this->sources as $source) {
            foreach ($source->notifications($etudiant, $depuis) as $notification) {
                if ($notification->date >= $depuis) {
                    $notifications[$notification->cle] = $notification;
                }
            }
        }

        $notifications = array_values($notifications);
        usort($notifications, fn (Notification $a, Notification $b) => $b->date <=> $a->date);

        return $notifications;
    }
}
