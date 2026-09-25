<?php

namespace App\Tests\Service\Notification;

use App\Entity\Users\Etudiant;
use App\Repository\Notification\ReadNotificationRepository;
use App\Service\Notification\NotificationCenter;
use App\Service\Notification\Notification;
use App\Service\Notification\Source\NotificationSourceInterface;
use PHPUnit\Framework\TestCase;

final class NotificationCenterTest extends TestCase
{
    private const MAINTENANT = '2026-09-24 12:00:00';

    /**
     * @param list<Notification> $notifications
     */
    private function source(array $notifications): NotificationSourceInterface
    {
        return new class ($notifications) implements NotificationSourceInterface {
            /**
             * @param list<Notification> $notifications
             */
            public function __construct(private readonly array $notifications)
            {
            }

            public function getNotifications(Etudiant $student, \DateTimeImmutable $since): iterable
            {
                return $this->notifications;
            }
        };
    }

    private function notification(string $key, string $date): Notification
    {
        return new Notification($key, Notification::TYPE_NOTE, $key, null, new \DateTimeImmutable($date), '/intranet/scolarite');
    }

    /**
     * @param list<string> $readKeys
     */
    private function readNotifications(array $readKeys = []): ReadNotificationRepository
    {
        $readNotifications = $this->createMock(ReadNotificationRepository::class);
        $readNotifications->method('readKeys')->willReturnCallback(
            fn (Etudiant $etudiant, array $keys) => array_values(array_intersect($keys, $readKeys)),
        );

        return $readNotifications;
    }

    public function testReunitLesSourcesDuPlusRecentAuPlusAncien(): void
    {
        $center = new NotificationCenter([
            $this->source([$this->notification('note-1', '2026-09-20')]),
            $this->source([$this->notification('message-1', '2026-09-23'), $this->notification('absence-1', '2026-09-10')]),
        ], $this->readNotifications());

        $keys = array_map(fn (array $e) => $e['notification']->key, $center->forStudent(new Etudiant(), new \DateTimeImmutable(self::MAINTENANT)));

        self::assertSame(['message-1', 'note-1', 'absence-1'], $keys);
    }

    public function testEcarteCeQuiDepasseLesQuatreVingtDixJours(): void
    {
        $center = new NotificationCenter([
            $this->source([$this->notification('note-recente', '2026-09-01'), $this->notification('note-ancienne', '2026-06-01')]),
        ], $this->readNotifications());

        $keys = array_map(fn (array $e) => $e['notification']->key, $center->forStudent(new Etudiant(), new \DateTimeImmutable(self::MAINTENANT)));

        self::assertSame(['note-recente'], $keys);
    }

    public function testDitCeQuiEstDejaLu(): void
    {
        $center = new NotificationCenter([
            $this->source([$this->notification('note-1', '2026-09-20'), $this->notification('note-2', '2026-09-21')]),
        ], $this->readNotifications(['note-1']));

        $reads = [];
        foreach ($center->forStudent(new Etudiant(), new \DateTimeImmutable(self::MAINTENANT)) as $element) {
            $reads[$element['notification']->key] = $element['lue'];
        }

        self::assertSame(['note-2' => false, 'note-1' => true], $reads);
    }

    public function testNeMarqueQueLesClesDuFilDeLEtudiant(): void
    {
        $readNotifications = $this->readNotifications();
        $readNotifications->expects(self::once())->method('mark')->with(self::isInstanceOf(Etudiant::class), ['note-1']);

        $center = new NotificationCenter([$this->source([$this->notification('note-1', '2026-09-20')])], $readNotifications);
        $center->markAsRead(new Etudiant(), ['note-1', 'note-d-un-autre'], new \DateTimeImmutable(self::MAINTENANT));
    }

    public function testMarqueToutLeFilSansCle(): void
    {
        $readNotifications = $this->readNotifications();
        $readNotifications->expects(self::once())->method('mark')->with(self::isInstanceOf(Etudiant::class), ['note-2', 'note-1']);

        $center = new NotificationCenter([
            $this->source([$this->notification('note-1', '2026-09-20'), $this->notification('note-2', '2026-09-21')]),
        ], $readNotifications);
        $center->markAsRead(new Etudiant(), null, new \DateTimeImmutable(self::MAINTENANT));
    }
}
