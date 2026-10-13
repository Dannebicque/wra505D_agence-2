<?php

declare(strict_types=1);

namespace App\Service\Timetable;

/**
 * Plage horaire de la grille d'un étudiant : de l'heure pleine avant son cours le plus matinal à
 * l'heure pleine après son cours le plus tardif. Calculée sur toute l'année, elle ne bouge pas
 * d'une semaine à l'autre : une grille qui change de hauteur chaque lundi désoriente.
 */
final class TimeRange
{
    /** Plage de l'intranet en ligne, pour un étudiant sans aucun cours connu. */
    public const DEFAULT_START = '08:00';
    public const DEFAULT_END = '20:00';

    /**
     * @param iterable<array{debut: \DateTimeInterface, fin: \DateTimeInterface}> $courses
     *
     * @return array{debut: string, fin: string}
     */
    public static function covering(iterable $courses): array
    {
        $starts = [];
        $ends = [];
        foreach ($courses as $course) {
            $starts[] = self::minutes($course['debut']);
            $ends[] = self::minutes($course['fin']);
        }

        if ([] === $starts) {
            return ['debut' => self::DEFAULT_START, 'fin' => self::DEFAULT_END];
        }

        return [
            'debut' => self::format(intdiv(min($starts), 60)),
            'fin' => self::format(min(24, intdiv(max($ends) + 59, 60))),
        ];
    }

    private static function minutes(\DateTimeInterface $time): int
    {
        return (int) $time->format('G') * 60 + (int) $time->format('i');
    }

    private static function format(int $hour): string
    {
        return sprintf('%02d:00', $hour);
    }
}
