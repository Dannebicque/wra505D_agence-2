<?php

declare(strict_types=1);

namespace App\EventListener\Doctrine;

use App\Entity\Contracts\TimestampableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Clock\ClockInterface;

/**
 * Date la création et la modification des entités horodatées.
 *
 * La modification est posée en onFlush plutôt qu'en preUpdate : une date changée en preUpdate
 * n'entre pas dans le jeu de modifications déjà calculé.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class TimestampableListener
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof TimestampableInterface) {
            return;
        }

        $now = $this->clock->now();
        if ($entity->getCreatedAt() === null) {
            $entity->setCreatedAt($now);
        }
        if ($entity->getUpdatedAt() === null) {
            $entity->setUpdatedAt($now);
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof TimestampableInterface) {
                continue;
            }
            $entity->setUpdatedAt($this->clock->now());
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);
        }
    }
}
