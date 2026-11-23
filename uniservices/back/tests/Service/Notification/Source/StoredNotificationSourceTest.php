<?php

namespace App\Tests\Service\Notification\Source;

use App\Entity\Notification\Notification as StoredNotification;
use App\Entity\Users\Etudiant;
use App\Service\Notification\Notification;
use App\Service\Notification\Source\StoredNotificationSource;
use PHPUnit\Framework\TestCase;

final class StoredNotificationSourceTest extends TestCase
{
    public function testTitreDuTypeEtLienDuModule(): void
    {
        $stored = new StoredNotification(new Etudiant(), 'stage.convention', 'stage', 'Convention validée', '/stage/convention');

        $item = StoredNotificationSource::toFeedItem($stored, 'Convention de stage');

        self::assertSame('module-'.$stored->getUuidString(), $item->key);
        self::assertSame(Notification::TYPE_MESSAGE, $item->type);
        self::assertSame('Convention de stage', $item->title);
        self::assertSame('Convention validée', $item->text);
        self::assertSame('/stage/convention', $item->link);
    }

    public function testSansTypeNiLienLeTexteSertDeTitre(): void
    {
        $stored = new StoredNotification(new Etudiant(), 'inconnu', 'intranet', 'Votre dossier a changé');

        $item = StoredNotificationSource::toFeedItem($stored, null);

        self::assertSame('Votre dossier a changé', $item->title);
        self::assertSame('/intranet/notifications', $item->link);
    }
}
