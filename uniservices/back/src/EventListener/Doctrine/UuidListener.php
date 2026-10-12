<?php

declare(strict_types=1);

namespace App\EventListener\Doctrine;

use App\Entity\Traits\UuidTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Donne un uuid aux entités de UuidTrait.
 *
 * Le rappel PrePersist du trait ne joue que si l'entité porte HasLifecycleCallbacks, que
 * l'horodatage par écouteur a retiré de la plupart d'entre elles.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class UuidListener
{
    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if (in_array(UuidTrait::class, class_uses($entity), true) && method_exists($entity, 'initializeUuid')) {
            $entity->initializeUuid();
        }
    }
}
