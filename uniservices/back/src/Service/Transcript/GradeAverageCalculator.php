<?php

declare(strict_types=1);

namespace App\Service\Transcript;

use App\Entity\Etudiant\EtudiantNote;

/**
 * Moyennes calculées à la volée, comme le prévoit le client tant qu'elles ne sont pas validées en
 * sous-commission. Aucune n'est enregistrée ici.
 *
 * Règles retenues, faute de règlement fourni :
 * - une note présente compte avec le coefficient de son évaluation ;
 * - une absence injustifiée compte pour 0 ;
 * - une absence justifiée et une dispense sont neutralisées ;
 * - une note présente mais pas encore saisie est ignorée, qu'elle soit vide ou à -0,01, la valeur
 *   que le formulaire de saisie du client utilise pour « non saisie ».
 */
final readonly class GradeAverageCalculator
{
    /**
     * @param list<array{note: ?float, coefficient: ?float, statut: ?string}> $grades
     */
    public function teachingAverage(array $grades): ?float
    {
        $weightedValues = [];
        foreach ($grades as $grade) {
            $value = match ($grade['statut']) {
                EtudiantNote::STATUT_ABSENT_INJUSTIFIE => 0.0,
                EtudiantNote::STATUT_ABSENT_JUSTIFIE, EtudiantNote::STATUT_DISPENSE => null,
                default => null !== $grade['note'] && $grade['note'] >= 0 ? $grade['note'] : null,
            };
            if (null !== $value) {
                $weightedValues[] = ['valeur' => $value, 'poids' => $grade['coefficient'] ?? 1.0];
            }
        }

        return $this->weightedAverage($weightedValues);
    }

    /**
     * @param list<array{moyenne: ?float, coefficient: float}> $teachings
     */
    public function courseAverage(array $teachings): ?float
    {
        $weightedValues = [];
        foreach ($teachings as $teaching) {
            if (null !== $teaching['moyenne']) {
                $weightedValues[] = ['valeur' => $teaching['moyenne'], 'poids' => $teaching['coefficient']];
            }
        }

        return $this->weightedAverage($weightedValues);
    }

    /**
     * @param list<array{valeur: float, poids: float}> $weightedValues
     */
    private function weightedAverage(array $weightedValues): ?float
    {
        $sum = 0.0;
        $weight = 0.0;
        foreach ($weightedValues as $weightedValue) {
            if ($weightedValue['poids'] <= 0) {
                continue;
            }
            $sum += $weightedValue['valeur'] * $weightedValue['poids'];
            $weight += $weightedValue['poids'];
        }

        return $weight > 0 ? round($sum / $weight, 2) : null;
    }
}
