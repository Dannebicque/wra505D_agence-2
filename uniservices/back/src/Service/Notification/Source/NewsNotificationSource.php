<?php

namespace App\Service\Notification\Source;

use App\Entity\Users\Etudiant;
use App\Repository\DepartementActualiteRepository;
use App\Repository\Structure\StructureDepartementRepository;
use App\Service\Notification\Notification;

/**
 * Une actualité de son département adressée aux étudiants. Elle se lit en entier sur l'accueil.
 */
final class NewsNotificationSource implements NotificationSourceInterface
{
    public function __construct(
        private readonly StructureDepartementRepository $departments,
        private readonly DepartementActualiteRepository $news,
    ) {
    }

    public function getNotifications(Etudiant $student, \DateTimeImmutable $since): iterable
    {
        $departement = $this->departments->findOneByEtudiant($student);
        if (null === $departement) {
            return [];
        }

        $notifications = [];
        foreach ($this->news->findByDepartementAndPublic($departement, 'etudiant') as $newsItem) {
            $date = $newsItem->getCreated();
            if (null === $date || $date < $since) {
                continue;
            }

            $notifications[] = new Notification(
                'actualite-'.$newsItem->getId(),
                Notification::TYPE_ACTUALITE,
                (string) $newsItem->getLibelle(),
                $newsItem->getDescription(),
                \DateTimeImmutable::createFromInterface($date),
                '/intranet/',
            );
        }

        return $notifications;
    }
}
