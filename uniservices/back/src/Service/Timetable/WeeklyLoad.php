<?php

declare(strict_types=1);

namespace App\Service\Timetable;

/**
 * Charge de cours d'un étudiant, semaine par semaine : de la semaine de son premier cours à celle
 * du dernier, sans trou. Une semaine de vacances vaut zéro minute : le sélecteur de semaines la
 * montre vide plutôt que de la sauter.
 */
final class WeeklyLoad
{
    /**
     * @param iterable<array{debut: \DateTimeInterface, fin: \DateTimeInterface}> $courses
     *
     * @return array<string, int> minutes de cours, par lundi au format AAAA-MM-JJ, dans l'ordre
     */
    public static function byMonday(iterable $courses): array
    {
        $minutes = [];
        foreach ($courses as $course) {
            $monday = self::monday($course['debut']);
            $length = intdiv(max(0, $course['fin']->getTimestamp() - $course['debut']->getTimestamp()), 60);
            $minutes[$monday] = ($minutes[$monday] ?? 0) + $length;
        }

        if ([] === $minutes) {
            return [];
        }

        ksort($minutes);
        $weeks = [];
        $week = new \DateTimeImmutable(array_key_first($minutes));
        $last = new \DateTimeImmutable(array_key_last($minutes));
        while ($week <= $last) {
            $key = $week->format('Y-m-d');
            $weeks[$key] = $minutes[$key] ?? 0;
            $week = $week->modify('+7 days');
        }

        return $weeks;
    }

    private static function monday(\DateTimeInterface $time): string
    {
        return \DateTimeImmutable::createFromInterface($time)->setTime(0, 0)->modify('monday this week')->format('Y-m-d');
    }
}
