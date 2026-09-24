<?php

namespace App\Service\Scolarite;

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
final class CalculMoyennes
{
    /**
     * @param list<array{note: ?float, coefficient: ?float, statut: ?string}> $notes
     */
    public function moyenneEnseignement(array $notes): ?float
    {
        $ponderees = [];
        foreach ($notes as $note) {
            $valeur = match ($note['statut']) {
                EtudiantNote::STATUT_ABSENT_INJUSTIFIE => 0.0,
                EtudiantNote::STATUT_ABSENT_JUSTIFIE, EtudiantNote::STATUT_DISPENSE => null,
                default => null !== $note['note'] && $note['note'] >= 0 ? $note['note'] : null,
            };
            if (null !== $valeur) {
                $ponderees[] = ['valeur' => $valeur, 'poids' => $note['coefficient'] ?? 1.0];
            }
        }

        return $this->moyennePonderee($ponderees);
    }

    /**
     * @param list<array{moyenne: ?float, coefficient: float}> $enseignements
     */
    public function moyenneUe(array $enseignements): ?float
    {
        $ponderees = [];
        foreach ($enseignements as $enseignement) {
            if (null !== $enseignement['moyenne']) {
                $ponderees[] = ['valeur' => $enseignement['moyenne'], 'poids' => $enseignement['coefficient']];
            }
        }

        return $this->moyennePonderee($ponderees);
    }

    /**
     * @param list<array{valeur: float, poids: float}> $ponderees
     */
    private function moyennePonderee(array $ponderees): ?float
    {
        $somme = 0.0;
        $poids = 0.0;
        foreach ($ponderees as $ponderee) {
            if ($ponderee['poids'] <= 0) {
                continue;
            }
            $somme += $ponderee['valeur'] * $ponderee['poids'];
            $poids += $ponderee['poids'];
        }

        return $poids > 0 ? round($somme / $poids, 2) : null;
    }
}
