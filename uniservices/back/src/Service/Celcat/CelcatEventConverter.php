<?php

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
     * @param array<string, mixed>           $ligne   ligne lue par CelcatReader::readEvents()
     * @param array<int, \DateTimeImmutable> $lundis  lundi de chaque semaine, indexé par numéro
     *
     * @return list<CelcatSlot>
     */
    public function convertir(array $ligne, array $lundis): array
    {
        // Le masque de la salle prime : une salle peut n'être réservée qu'une partie des
        // semaines du cours. Même ordre de priorité que l'intranet V3.
        $masque = (string) ($ligne['room_weeks'] ?? $ligne['weeks'] ?? '');
        $jour = (int) $ligne['day_of_week'];
        $estUnCours = '' !== (string) ($ligne['module_code'] ?? '');
        $categorie = $this->texte($ligne['category'] ?? null) ?? '';

        $creneaux = [];
        $longueur = strlen($masque);
        for ($semaine = 0; $semaine < $longueur; ++$semaine) {
            if ('Y' !== strtoupper($masque[$semaine])) {
                continue;
            }

            // Celcat peut marquer des semaines absentes de son propre calendrier : l'intranet
            // V3 plantait alors sur un index inconnu, on ignore la semaine.
            if (!isset($lundis[$semaine])) {
                continue;
            }

            $date = $lundis[$semaine]->modify('+'.$jour.' days');

            $creneaux[] = new CelcatSlot(
                celcatId: (int) $ligne['event_id'],
                semaine: $semaine,
                jour: $jour,
                date: $date,
                debut: $this->heure($date, (string) $ligne['start_time']),
                fin: $this->heure($date, (string) $ligne['end_time']),
                estUnCours: $estUnCours,
                // La catégorie arrive entre crochets, « [TD] ». Pour un événement qui n'est pas
                // un cours, le type dépend du groupe : c'est au synchroniseur de le déduire.
                type: $estUnCours ? mb_substr($categorie, 1, -1) : null,
                codeModule: $estUnCours ? (string) $ligne['module_code'] : (string) $ligne['event_id'],
                libModule: $estUnCours
                    ? ($this->texte($ligne['module_name'] ?? null) ?? '')
                    : trim($categorie.' '.($this->texte($ligne['notes'] ?? null) ?? '')),
                codePersonnel: $this->texte($ligne['staff_code'] ?? null),
                libPersonnel: $this->texte($ligne['staff_name'] ?? null),
                codeSalle: $this->texte($ligne['room_code'] ?? null),
                libSalle: $this->texte($ligne['room_name'] ?? null),
                codeGroupe: $this->texte($ligne['group_code'] ?? null),
                libGroupe: $this->texte($ligne['group_name'] ?? null),
                modifieLe: $this->dateHeure($ligne['date_change'] ?? null),
            );
        }

        return $creneaux;
    }

    /**
     * Celcat range une heure dans une date fictive, « 1899-12-30 08:00:00 » : seule la
     * partie horaire compte, reportée sur le jour du créneau.
     */
    private function heure(\DateTimeImmutable $jour, string $valeur): \DateTimeImmutable
    {
        $morceaux = explode(' ', trim($valeur));
        [$h, $m, $s] = array_map('intval', explode(':', end($morceaux)) + [0, 0, 0]);

        return $jour->setTime($h, $m, $s);
    }

    private function dateHeure(mixed $valeur): ?\DateTimeImmutable
    {
        if (null === $valeur || '' === $valeur) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr((string) $valeur, 0, 19));

        return false === $date ? null : $date;
    }

    /**
     * La base Celcat de l'université renvoie du Latin-1 ; l'intranet V3 appelait
     * utf8_encode(), déprécié depuis PHP 8.2. On ne convertit que ce qui n'est pas déjà
     * de l'UTF-8, pour qu'une base configurée en UTF-8 passe aussi.
     */
    private function texte(mixed $valeur): ?string
    {
        if (null === $valeur) {
            return null;
        }

        $texte = trim((string) $valeur);
        if ('' === $texte) {
            return null;
        }

        return mb_check_encoding($texte, 'UTF-8') ? $texte : mb_convert_encoding($texte, 'UTF-8', 'ISO-8859-1');
    }
}
