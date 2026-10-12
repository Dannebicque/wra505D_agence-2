<?php

declare(strict_types=1);

namespace App\Tests\EventListener\Doctrine;

use App\Entity\Contracts\TimestampableInterface;
use App\Entity\Traits\TimestampableTrait;
use App\EventListener\Doctrine\TimestampableListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Les dates de création et de modification sont posées par l'écouteur, plus par l'entité.
 */
final class TimestampableListenerTest extends TestCase
{
    /**
     * Une entité neuve reçoit les deux dates ; une date déjà fixée, par un import par exemple, est
     * gardée.
     */
    public function testDatesANewEntityWithoutOverwritingAGivenDate(): void
    {
        $clock = new MockClock('2026-10-12 09:00:00');
        $listener = new TimestampableListener($clock);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entity = self::entity();
        $listener->prePersist(new PrePersistEventArgs($entity, $entityManager));

        self::assertEquals($clock->now(), $entity->getCreatedAt());
        self::assertEquals($clock->now(), $entity->getUpdatedAt());

        $imported = self::entity();
        $imported->setCreatedAt(new \DateTimeImmutable('2019-12-09 21:38:01'));
        $listener->prePersist(new PrePersistEventArgs($imported, $entityManager));

        self::assertEquals(new \DateTimeImmutable('2019-12-09 21:38:01'), $imported->getCreatedAt());
    }

    /**
     * Une entité modifiée change de date de modification, et son jeu de modifications est
     * recalculé pour que la nouvelle date soit écrite.
     */
    public function testUpdatesTheModificationDateOnFlush(): void
    {
        $clock = new MockClock('2026-10-12 09:00:00');
        $entity = self::entity();
        $entity->setCreatedAt(new \DateTimeImmutable('2026-01-01'));
        $entity->setUpdatedAt(new \DateTimeImmutable('2026-01-01'));

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([$entity, new \stdClass()]);
        $unitOfWork->expects(self::once())->method('recomputeSingleEntityChangeSet');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(ClassMetadata::class));

        (new TimestampableListener($clock))->onFlush(new OnFlushEventArgs($entityManager));

        self::assertEquals(new \DateTimeImmutable('2026-01-01'), $entity->getCreatedAt());
        self::assertEquals($clock->now(), $entity->getUpdatedAt());
    }

    private static function entity(): TimestampableInterface
    {
        return new class () implements TimestampableInterface {
            use TimestampableTrait;
        };
    }
}
