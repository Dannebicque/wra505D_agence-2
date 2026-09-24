<?php

namespace App\Service\Notification\Source;

use App\Entity\Users\Etudiant;
use App\Repository\DepartementActualiteRepository;
use App\Repository\Structure\StructureDepartementRepository;
use App\Service\Notification\Notification;

/**
 * Une actualité de son département adressée aux étudiants. Elle se lit en entier sur l'accueil.
 */
final class SourceActualites implements SourceNotificationInterface
{
    public function __construct(
        private readonly StructureDepartementRepository $departements,
        private readonly DepartementActualiteRepository $actualites,
    ) {
    }

    public function notifications(Etudiant $etudiant, \DateTimeImmutable $depuis): iterable
    {
        $departement = $this->departements->findOneByEtudiant($etudiant);
        if (null === $departement) {
            return [];
        }

        $notifications = [];
        foreach ($this->actualites->findByDepartementAndPublic($departement, 'etudiant') as $actualite) {
            $date = $actualite->getCreated();
            if (null === $date || $date < $depuis) {
                continue;
            }

            $notifications[] = new Notification(
                'actualite-'.$actualite->getId(),
                Notification::TYPE_ACTUALITE,
                (string) $actualite->getLibelle(),
                $actualite->getDescription(),
                \DateTimeImmutable::createFromInterface($date),
                '/intranet/',
            );
        }

        return $notifications;
    }
}
