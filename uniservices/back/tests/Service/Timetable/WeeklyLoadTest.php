<?php

declare(strict_types=1);

namespace App\Tests\Service\Timetable;

use App\Service\Timetable\WeeklyLoad;
use PHPUnit\Framework\TestCase;

final class WeeklyLoadTest extends TestCase
{
    /**
     * @return array{debut: \DateTimeInterface, fin: \DateTimeInterface}
     */
    private function course(string $start, string $end): array
    {
        return ['debut' => new \DateTimeImmutable($start), 'fin' => new \DateTimeImmutable($end)];
    }

    public function testAdditionneLesMinutesDeChaqueSemaine(): void
    {
        self::assertSame(['2026-10-12' => 195], WeeklyLoad::byMonday([
            $this->course('2026-10-12 08:00', '2026-10-12 10:00'),
            $this->course('2026-10-16 13:30', '2026-10-16 14:45'),
        ]));
    }

    public function testRangeUnCoursDuSamediDansSaSemaine(): void
    {
        self::assertSame(['2026-09-14' => 180], WeeklyLoad::byMonday([$this->course('2026-09-19 09:00', '2026-09-19 12:00')]));
    }

    public function testGardeLesSemainesSansCoursEntreDeuxSemainesDeCours(): void
    {
        self::assertSame(
            ['2026-10-19' => 60, '2026-10-26' => 0, '2026-11-02' => 45],
            WeeklyLoad::byMonday([
                $this->course('2026-11-02 08:00', '2026-11-02 08:45'),
                $this->course('2026-10-19 08:00', '2026-10-19 09:00'),
            ]),
        );
    }

    public function testNeRendRienSansCours(): void
    {
        self::assertSame([], WeeklyLoad::byMonday([]));
    }
}
