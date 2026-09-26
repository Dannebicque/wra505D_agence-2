<?php

declare(strict_types=1);

namespace App\Tests\Service\Celcat;

use App\Service\Celcat\CelcatEventConverter;
use PHPUnit\Framework\TestCase;

final class CelcatEventConverterTest extends TestCase
{
    private CelcatEventConverter $converter;

    /** @var array<int, \DateTimeImmutable> */
    private array $mondays;

    protected function setUp(): void
    {
        $this->converter = new CelcatEventConverter();
        $this->mondays = [
            0 => new \DateTimeImmutable('2026-09-07'),
            1 => new \DateTimeImmutable('2026-09-14'),
            2 => new \DateTimeImmutable('2026-09-21'),
            3 => new \DateTimeImmutable('2026-09-28'),
        ];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function row(array $override = []): array
    {
        return array_merge([
            'event_id' => 4210,
            'day_of_week' => 2,
            'start_time' => '1899-12-30 08:00:00',
            'end_time' => '1899-12-30 10:00:00',
            'weeks' => 'YYYY',
            'category' => '[TD]',
            'module_code' => 'R5.01',
            'module_name' => 'Développement front avancé',
            'staff_code' => '1234',
            'staff_name' => 'Martin Claire',
            'room_code' => 'B204',
            'room_name' => 'Salle B204',
            'group_code' => 'MMI3-TD2',
            'group_name' => 'TD2',
            'date_change' => '2026-09-01 17:42:00',
            'room_weeks' => null,
            'notes' => null,
        ], $override);
    }

    public function testExpandsMaskPerMarkedWeek(): void
    {
        $slots = $this->converter->convert($this->row(['weeks' => 'NYNY']), $this->mondays);

        self::assertSame([1, 3], array_map(fn ($c) => $c->week, $slots));
    }

    public function testAcceptsLowercaseMask(): void
    {
        $slots = $this->converter->convert($this->row(['weeks' => 'yNyN']), $this->mondays);

        self::assertSame([0, 2], array_map(fn ($c) => $c->week, $slots));
    }

    public function testDatesSlotFromMondayAndDay(): void
    {
        [$event] = $this->converter->convert($this->row(['weeks' => 'NNY', 'day_of_week' => 2]), $this->mondays);

        self::assertSame('2026-09-23', $event->date->format('Y-m-d'));
        self::assertSame('Wednesday', $event->date->format('l'));
    }

    public function testMovesTimesToSlotDay(): void
    {
        [$event] = $this->converter->convert($this->row(['weeks' => 'Y', 'day_of_week' => 0]), $this->mondays);

        self::assertSame('2026-09-07 08:00:00', $event->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-07 10:00:00', $event->end->format('Y-m-d H:i:s'));
    }

    public function testRoomMaskTakesPriority(): void
    {
        $slots = $this->converter->convert($this->row(['weeks' => 'YYYY', 'room_weeks' => 'YNNN']), $this->mondays);

        self::assertSame([0], array_map(fn ($c) => $c->week, $slots));
    }

    public function testFallsBackToCourseMask(): void
    {
        $slots = $this->converter->convert($this->row(['weeks' => 'NNYY', 'room_weeks' => null]), $this->mondays);

        self::assertSame([2, 3], array_map(fn ($c) => $c->week, $slots));
    }

    public function testRemovesTypeBrackets(): void
    {
        [$event] = $this->converter->convert($this->row(['weeks' => 'Y', 'category' => '[TP]']), $this->mondays);

        self::assertTrue($event->isCourse);
        self::assertSame('TP', $event->type);
        self::assertSame('R5.01', $event->moduleCode);
    }

    public function testTreatsEventWithoutSubjectAsNonCourse(): void
    {
        [$event] = $this->converter->convert($this->row([
            'weeks' => 'Y',
            'module_code' => null,
            'category' => 'Réunion',
            'notes' => 'rentrée BUT 3',
        ]), $this->mondays);

        self::assertFalse($event->isCourse);
        self::assertNull($event->type);
        self::assertSame('4210', $event->moduleCode);
        self::assertSame('Réunion rentrée BUT 3', $event->moduleLabel);
    }

    public function testIgnoresWeekOutsideCalendar(): void
    {
        $slots = $this->converter->convert($this->row(['weeks' => 'YNNNNNNY']), $this->mondays);

        self::assertSame([0], array_map(fn ($c) => $c->week, $slots));
    }

    public function testConvertsLatin1ToUtf8(): void
    {
        [$event] = $this->converter->convert($this->row([
            'weeks' => 'Y',
            'module_name' => mb_convert_encoding('Développement', 'ISO-8859-1', 'UTF-8'),
        ]), $this->mondays);

        self::assertSame('Développement', $event->moduleLabel);
    }

    public function testKeepsUtf8Text(): void
    {
        [$event] = $this->converter->convert($this->row(['weeks' => 'Y', 'staff_name' => 'Lefèvre Hélène']), $this->mondays);

        self::assertSame('Lefèvre Hélène', $event->staffLabel);
    }

    public function testTreatsEmptyFieldAsAbsent(): void
    {
        [$event] = $this->converter->convert($this->row(['weeks' => 'Y', 'staff_code' => '', 'room_name' => '   ']), $this->mondays);

        self::assertNull($event->staffCode);
        self::assertNull($event->roomLabel);
    }

    public function testKeysSlotByCourseWeekDayAndGroup(): void
    {
        [$event] = $this->converter->convert($this->row(['weeks' => 'NNNY']), $this->mondays);

        self::assertSame('4210_3_2_MMI3-TD2', $event->key());
    }

    public function testDistinguishesSharedCmByGroup(): void
    {
        [$forTd1] = $this->converter->convert($this->row(['weeks' => 'Y', 'group_code' => 'MMI3-TD1']), $this->mondays);
        [$forTd2] = $this->converter->convert($this->row(['weeks' => 'Y', 'group_code' => 'MMI3-TD2']), $this->mondays);

        self::assertNotSame($forTd1->key(), $forTd2->key());
    }

    public function testReadsLastModificationDate(): void
    {
        [$event] = $this->converter->convert($this->row(['weeks' => 'Y']), $this->mondays);

        self::assertSame('2026-09-01 17:42:00', $event->changedAt?->format('Y-m-d H:i:s'));
    }

    public function testProducesNothingForEmptyMask(): void
    {
        self::assertSame([], $this->converter->convert($this->row(['weeks' => '', 'room_weeks' => null]), $this->mondays));
    }
}
