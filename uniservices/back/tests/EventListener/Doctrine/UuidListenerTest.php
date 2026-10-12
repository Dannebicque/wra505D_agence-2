<?php

declare(strict_types=1);

namespace App\Tests\EventListener\Doctrine;

use App\Entity\Traits\UuidTrait;
use App\EventListener\Doctrine\UuidListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Sans HasLifecycleCallbacks, c'est l'écouteur qui donne son uuid à une entité neuve.
 */
final class UuidListenerTest extends TestCase
{
    public function testGivesAnUuidOnlyWhenMissing(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $listener = new UuidListener();

        $entity = new class () {
            use UuidTrait;
        };
        $listener->prePersist(new PrePersistEventArgs($entity, $entityManager));
        self::assertInstanceOf(Uuid::class, $entity->getUuid());

        $given = Uuid::v4();
        $imported = new class () {
            use UuidTrait;
        };
        $imported->setUuid($given);
        $listener->prePersist(new PrePersistEventArgs($imported, $entityManager));
        self::assertTrue($given->equals($imported->getUuid()));
    }
}
