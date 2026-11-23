<?php

declare(strict_types=1);

namespace App\Service\Subject;

use App\Utils\LooseValue;

/**
 * Extrait d'un relevé la part d'une matière : son semestre, ses UE, ses notes et ses absences.
 *
 * Règles retenues :
 * - seule l'année en cours compte : la fiche sert à suivre la matière, pas à relire l'historique ;
 * - une matière partagée entre plusieurs UE n'a qu'une moyenne et qu'une liste d'évaluations, le
 *   relevé les répétant à l'identique dans chaque UE ; seuls le coefficient et le poids changent ;
 * - les absences du semestre sont rattachées à la matière par son code, unique dans un semestre.
 */
final readonly class SubjectSheetAssembler
{
    /**
     * Null si la matière n'est dans aucun semestre de l'année en cours.
     *
     * @param list<array<string, mixed>> $semesters les semestres de TranscriptBuilder::build()
     *
     * @return array{matiere: array<string, mixed>, notes: array<string, mixed>, absences: list<array<array-key, mixed>>}|null
     */
    public function assemble(array $semesters, int $teachingId): ?array
    {
        foreach ($semesters as $semester) {
            if (true !== ($semester['actif'] ?? false)) {
                continue;
            }

            $teaching = null;
            $courses = [];
            foreach (LooseValue::rows($semester['ues'] ?? []) as $course) {
                $teachings = LooseValue::rows($course['enseignements'] ?? []);
                foreach ($teachings as $candidate) {
                    if ($teachingId !== ($candidate['id'] ?? null)) {
                        continue;
                    }
                    $teaching ??= $candidate;
                    $courses[] = [
                        'numero' => $course['numero'] ?? null,
                        'libelle' => $course['libelle'] ?? null,
                        'coefficient' => $candidate['coefficient'] ?? null,
                        'poids' => $this->weight($teachings, $candidate),
                    ];
                }
            }
            if (null === $teaching) {
                continue;
            }

            $absences = LooseValue::row($semester['absences'] ?? []);

            return [
                'matiere' => [
                    'id' => $teachingId,
                    'code' => $teaching['code'] ?? null,
                    'libelle' => $teaching['libelle'] ?? null,
                    'type' => $teaching['type'] ?? null,
                    'semestre' => $semester['libelle'] ?? null,
                    'anneeUniversitaire' => $semester['anneeUniversitaire'] ?? null,
                    'ues' => $courses,
                ],
                'notes' => [
                    'moyenne' => $teaching['moyenne'] ?? null,
                    'moyennePenalisee' => $teaching['moyennePenalisee'] ?? null,
                    'penalite' => $teaching['penalite'] ?? 0.0,
                    'absencesPenalisantes' => $teaching['absencesPenalisantes'] ?? 0,
                    'absencesEnAttente' => $teaching['absencesEnAttente'] ?? 0,
                    'evaluations' => $teaching['evaluations'] ?? [],
                ],
                'absences' => array_values(array_filter(
                    LooseValue::rows($absences['liste'] ?? []),
                    static fn (array $absence): bool => null !== ($absence['code'] ?? null) && ($teaching['code'] ?? null) === $absence['code'],
                )),
            ];
        }

        return null;
    }

    /**
     * Part de la matière dans la moyenne de l'UE, en pourcentage de la somme des coefficients,
     * comme l'affiche la page Notes et absences.
     *
     * @param array<array-key, array<array-key, mixed>> $teachings
     * @param array<array-key, mixed>                   $teaching
     */
    private function weight(array $teachings, array $teaching): int
    {
        $total = 0.0;
        foreach ($teachings as $candidate) {
            $total += is_numeric($candidate['coefficient'] ?? null) ? (float) $candidate['coefficient'] : 0.0;
        }
        $coefficient = is_numeric($teaching['coefficient'] ?? null) ? (float) $teaching['coefficient'] : 0.0;

        return $total > 0 ? (int) round($coefficient / $total * 100) : 0;
    }
}
