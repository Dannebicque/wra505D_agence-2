<?php

declare(strict_types=1);

namespace App\Service\Celcat;

/**
 * Transforme une ligne d'événement Celcat en créneaux datés.
 *
 * Reprend les règles de MyCelcat::transformeCelcatToDto() de l'intranet V3 : une ligne
 * Celcat décrit un cours récurrent, et son masque de semaines dit lesquelles il occupe.
 */
final class CelcatEventConverter
{
    /**
     * @param array<string, mixed>           $row     ligne lue par CelcatReader::readEvents()
     * @param array<int, \DateTimeImmutable> $mondays  lundi de chaque semaine, indexé par numéro
     *
     * @return list<CelcatSlot>
     */
    public function convert(array $row, array $mondays): array
    {
        // Le masque de la salle prime : une salle peut n'être réservée qu'une partie des
        // semaines du cours. Même ordre de priorité que l'intranet V3.
        $mask = (string) ($row['room_weeks'] ?? $row['weeks'] ?? '');
        $day = (int) $row['day_of_week'];
        $isCourse = '' !== (string) ($row['module_code'] ?? '');
        $category = $this->text($row['category'] ?? null) ?? '';

        $slots = [];
        $length = strlen($mask);
        for ($week = 0; $week < $length; ++$week) {
            if ('Y' !== strtoupper($mask[$week])) {
                continue;
            }

            // Celcat peut marquer des semaines absentes de son propre calendrier : l'intranet
            // V3 plantait alors sur un index inconnu, on ignore la semaine.
            if (!isset($mondays[$week])) {
                continue;
            }

            $date = $mondays[$week]->modify('+'.$day.' days');

            $slots[] = new CelcatSlot(
                celcatId: (int) $row['event_id'],
                week: $week,
                day: $day,
                date: $date,
                start: $this->time($date, (string) $row['start_time']),
                end: $this->time($date, (string) $row['end_time']),
                isCourse: $isCourse,
                // La catégorie arrive entre crochets, « [TD] ». Pour un événement qui n'est pas
                // un cours, le type dépend du groupe : c'est au synchroniseur de le déduire.
                type: $isCourse ? mb_substr($category, 1, -1) : null,
                moduleCode: $isCourse ? (string) $row['module_code'] : (string) $row['event_id'],
                moduleLabel: $isCourse
                    ? ($this->text($row['module_name'] ?? null) ?? '')
                    : trim($category.' '.($this->text($row['notes'] ?? null) ?? '')),
                staffCode: $this->text($row['staff_code'] ?? null),
                staffLabel: $this->text($row['staff_name'] ?? null),
                roomCode: $this->text($row['room_code'] ?? null),
                roomLabel: $this->text($row['room_name'] ?? null),
                groupCode: $this->text($row['group_code'] ?? null),
                groupLabel: $this->text($row['group_name'] ?? null),
                changedAt: $this->dateTime($row['date_change'] ?? null),
            );
        }

        return $slots;
    }

    /**
     * Celcat range une heure dans une date fictive, « 1899-12-30 08:00:00 » : seule la
     * partie horaire compte, reportée sur le jour du créneau.
     */
    private function time(\DateTimeImmutable $day, string $value): \DateTimeImmutable
    {
        $parts = explode(' ', trim($value));
        [$h, $m, $s] = array_map('intval', explode(':', end($parts)) + [0, 0, 0]);

        return $day->setTime($h, $m, $s);
    }

    private function dateTime(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr((string) $value, 0, 19));

        return false === $date ? null : $date;
    }

    /**
     * La base Celcat de l'université renvoie du Latin-1 ; l'intranet V3 appelait
     * utf8_encode(), déprécié depuis PHP 8.2. On ne convertit que ce qui n'est pas déjà
     * de l'UTF-8, pour qu'une base configurée en UTF-8 passe aussi.
     */
    private function text(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $text = trim((string) $value);
        if ('' === $text) {
            return null;
        }

        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }
}
