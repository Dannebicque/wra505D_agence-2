<?php

declare(strict_types=1);

namespace App\Tests\Service\Timetable;

use App\Service\Timetable\TimeRange;
use PHPUnit\Framework\TestCase;

final class TimeRangeTest extends TestCase
{
    /**
     * @return array{debut: \DateTimeInterface, fin: \DateTimeInterface}
     */
    private function course(string $day, string $start, string $end): array
    {
        return ['debut' => new \DateTimeImmutable($day.' '.$start), 'fin' => new \DateTimeImmutable($day.' '.$end)];
    }

    public function testCouvreDuPremierCoursAuDernierSurToutesLesSemaines(): void
    {
        $range = TimeRange::covering([
            $this->course('2026-09-14', '08:00', '10:00'),
            $this->course('2026-10-19', '17:00', '19:00'),
            $this->course('2026-12-02', '13:30', '15:00'),
        ]);

        self::assertSame(['debut' => '08:00', 'fin' => '19:00'], $range);
    }

    public function testArrondiALHeurePleineQuiEnglobeLesCours(): void
    {
        $range = TimeRange::covering([
            $this->course('2026-09-14', '08:15', '10:00'),
            $this->course('2026-09-15', '16:45', '18:15'),
        ]);

        self::assertSame(['debut' => '08:00', 'fin' => '19:00'], $range);
    }

    public function testDescendAvantHuitHeuresPourUnCoursMatinal(): void
    {
        self::assertSame('07:00', TimeRange::covering([$this->course('2026-09-18', '07:45', '08:45')])['debut']);
    }

    public function testGardeLaPlageDeLIntranetSansAucunCours(): void
    {
        self::assertSame(['debut' => '08:00', 'fin' => '20:00'], TimeRange::covering([]));
    }

    public function testNeDepassePasMinuit(): void
    {
        self::assertSame('24:00', TimeRange::covering([$this->course('2026-09-14', '22:00', '23:59')])['fin']);
    }
}
