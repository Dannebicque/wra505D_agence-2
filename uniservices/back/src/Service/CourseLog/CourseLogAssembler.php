<?php

declare(strict_types=1);

namespace App\Service\CourseLog;

use App\Entity\Scolarite\ScolEvaluation;
use App\Enum\EtatEvaluationEnum;
use IntranetBundle\Entity\PlanCours\PlanCours;
use IntranetBundle\Enum\PlanCours\PlanCoursEnum;

/**
 * Met en forme le cahier de texte à partir de ce que les enseignants saisissent déjà : les
 * évaluations datées et les plans de cours.
 *
 * Règles retenues, le client n'ayant pas défini le contenu de la page :
 * - une échéance est une évaluation datée d'aujourd'hui ou plus tard, sauf annulée ;
 * - un plan de cours n'est montré que « Complet » : « En cours » est un brouillon ;
 * - les champs de suivi et les commentaires, destinés à l'équipe pédagogique, ne sortent pas.
 */
final readonly class CourseLogAssembler
{
    /**
     * @param iterable<ScolEvaluation> $evaluations
     *
     * @return list<array{date: string, libelle: ?string, type: ?string, coefficient: ?float, code: ?string, matiere: ?string}>
     */
    public function deadlines(iterable $evaluations, \DateTimeImmutable $today): array
    {
        $deadlines = [];
        foreach ($evaluations as $evaluation) {
            $date = $evaluation->getDate()?->format('Y-m-d');
            if (null === $date || $date < $today->format('Y-m-d') || EtatEvaluationEnum::ETAT_ANNULEE === $evaluation->getEtat()) {
                continue;
            }
            $deadlines[] = [
                'date' => $date,
                'libelle' => $evaluation->getLibelle(),
                'type' => $evaluation->getType()?->value,
                'coefficient' => $evaluation->getCoeff(),
                'code' => $evaluation->getEnseignement()?->getCodeEnseignement(),
                'matiere' => $evaluation->getEnseignement()?->getLibelle(),
            ];
        }
        usort($deadlines, static fn (array $a, array $b): int => [$a['date'], $a['libelle']] <=> [$b['date'], $b['libelle']]);

        return $deadlines;
    }

    /**
     * @param iterable<PlanCours> $plans
     *
     * @return list<array<string, mixed>>
     */
    public function subjects(iterable $plans): array
    {
        $subjects = [];
        foreach ($plans as $plan) {
            $teaching = $plan->getEnseignement();
            if (null === $teaching || PlanCoursEnum::COMPLET !== $plan->getEtatPlanCours()) {
                continue;
            }

            $sequences = [];
            foreach ($plan->getPlanCoursSequences() as $sequence) {
                $sequences[] = [
                    'ordre' => $sequence->getOrdre(),
                    'programme' => $this->text($sequence->getProgramme()),
                    'duree' => $this->text($sequence->getNbHeures()),
                ];
            }

            $sessions = [];
            foreach ($plan->getPlanCoursRealises() as $session) {
                $sessions[] = [
                    'contenu' => $this->text($session->getContenu()),
                    'heures' => $session->getNbHeures(),
                ];
            }

            $subjects[] = [
                'code' => $teaching->getCodeEnseignement(),
                'libelle' => $teaching->getLibelle(),
                'type' => $teaching->getType()->getLibelle(),
                'responsable' => $this->text($plan->getResponsable()?->getDisplay()),
                'objectifs' => $this->text($plan->getObjectifs()),
                'modalitesEvaluations' => $this->text($plan->getModalitesEvaluations()),
                'livrables' => $this->text($plan->getLivrables()),
                'deroule' => $this->text($plan->getSaisiePlanCours()),
                'sequences' => $sequences,
                'seances' => $sessions,
            ];
        }
        usort($subjects, static fn (array $a, array $b): int => [$a['code'], $a['libelle']] <=> [$b['code'], $b['libelle']]);

        return $subjects;
    }

    private function text(?string $value): ?string
    {
        $text = trim($value ?? '');

        return '' === $text ? null : $text;
    }
}
