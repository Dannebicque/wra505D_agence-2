<?php

namespace App\Service\Notification\Source;

use App\Entity\Users\Etudiant;
use App\Service\Notification\Notification;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un type de notification. Ajouter un type au centre de notifications, c'est ajouter une source.
 */
#[AutoconfigureTag(self::TAG)]
interface SourceNotificationInterface
{
    public const TAG = 'app.source_notification';

    /**
     * Les événements de cet étudiant survenus depuis cette date.
     *
     * @return iterable<Notification>
     */
    public function notifications(Etudiant $etudiant, \DateTimeImmutable $depuis): iterable;
}
