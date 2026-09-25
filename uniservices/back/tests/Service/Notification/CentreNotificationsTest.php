<?php

namespace App\Tests\Service\Notification;

use App\Entity\Users\Etudiant;
use App\Repository\Notification\NotificationLueRepository;
use App\Service\Notification\CentreNotifications;
use App\Service\Notification\Notification;
use App\Service\Notification\Source\SourceNotificationInterface;
use PHPUnit\Framework\TestCase;

final class CentreNotificationsTest extends TestCase
{
    private const MAINTENANT = '2026-09-24 12:00:00';

    /**
     * @param list<Notification> $notifications
     */
    private function source(array $notifications): SourceNotificationInterface
    {
        return new class($notifications) implements SourceNotificationInterface {
            /**
             * @param list<Notification> $notifications
             */
            public function __construct(private readonly array $notifications)
            {
            }

            public function notifications(Etudiant $etudiant, \DateTimeImmutable $depuis): iterable
            {
                return $this->notifications;
            }
        };
    }

    private function notification(string $cle, string $date): Notification
    {
        return new Notification($cle, Notification::TYPE_NOTE, $cle, null, new \DateTimeImmutable($date), '/intranet/scolarite');
    }

    /**
     * @param list<string> $clesLues
     */
    private function lectures(array $clesLues = []): NotificationLueRepository
    {
        $lectures = $this->createMock(NotificationLueRepository::class);
        $lectures->method('clesLues')->willReturnCallback(
            fn (Etudiant $etudiant, array $cles) => array_values(array_intersect($cles, $clesLues)),
        );

        return $lectures;
    }

    public function testReunitLesSourcesDuPlusRecentAuPlusAncien(): void
    {
        $centre = new CentreNotifications([
            $this->source([$this->notification('note-1', '2026-09-20')]),
            $this->source([$this->notification('message-1', '2026-09-23'), $this->notification('absence-1', '2026-09-10')]),
        ], $this->lectures());

        $cles = array_map(fn (array $e) => $e['notification']->cle, $centre->pour(new Etudiant(), new \DateTimeImmutable(self::MAINTENANT)));

        self::assertSame(['message-1', 'note-1', 'absence-1'], $cles);
    }

    public function testEcarteCeQuiDepasseLesQuatreVingtDixJours(): void
    {
        $centre = new CentreNotifications([
            $this->source([$this->notification('note-recente', '2026-09-01'), $this->notification('note-ancienne', '2026-06-01')]),
        ], $this->lectures());

        $cles = array_map(fn (array $e) => $e['notification']->cle, $centre->pour(new Etudiant(), new \DateTimeImmutable(self::MAINTENANT)));

        self::assertSame(['note-recente'], $cles);
    }

    public function testDitCeQuiEstDejaLu(): void
    {
        $centre = new CentreNotifications([
            $this->source([$this->notification('note-1', '2026-09-20'), $this->notification('note-2', '2026-09-21')]),
        ], $this->lectures(['note-1']));

        $lues = [];
        foreach ($centre->pour(new Etudiant(), new \DateTimeImmutable(self::MAINTENANT)) as $element) {
            $lues[$element['notification']->cle] = $element['lue'];
        }

        self::assertSame(['note-2' => false, 'note-1' => true], $lues);
    }

    public function testNeMarqueQueLesClesDuFilDeLEtudiant(): void
    {
        $lectures = $this->lectures();
        $lectures->expects(self::once())->method('marquer')->with(self::isInstanceOf(Etudiant::class), ['note-1']);

        $centre = new CentreNotifications([$this->source([$this->notification('note-1', '2026-09-20')])], $lectures);
        $centre->marquerLues(new Etudiant(), ['note-1', 'note-d-un-autre'], new \DateTimeImmutable(self::MAINTENANT));
    }

    public function testMarqueToutLeFilSansCle(): void
    {
        $lectures = $this->lectures();
        $lectures->expects(self::once())->method('marquer')->with(self::isInstanceOf(Etudiant::class), ['note-2', 'note-1']);

        $centre = new CentreNotifications([
            $this->source([$this->notification('note-1', '2026-09-20'), $this->notification('note-2', '2026-09-21')]),
        ], $lectures);
        $centre->marquerLues(new Etudiant(), null, new \DateTimeImmutable(self::MAINTENANT));
    }
}
