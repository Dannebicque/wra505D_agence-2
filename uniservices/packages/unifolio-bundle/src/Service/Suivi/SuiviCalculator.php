<?php

namespace UnifolioBundle\Service\Suivi;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Utils\LooseValue;
use UnifolioBundle\Service\Evaluation\AutoEvaluationManager;
use UnifolioBundle\Service\Evaluation\BilanAnnee;
use UnifolioBundle\Service\Trace\UnifolioValidationException;

/**
 * Progression d'un étudiant sur UNE année de BUT, à la granularité choisie par la formation.
 * Un élément est « couvert » quand au moins une trace déposée (ou en cours d'évaluation / évaluée) lui est rattachée.
 * Les scores sont normalisés entre 0 et 1.
 */
class SuiviCalculator
{
    private const STATUTS_COUVRANTS = ['deposee', 'en_evaluation', 'evaluee'];
    /** Écart auto-évaluation / évaluation à partir duquel on le signale (comme la comparaison de l'évaluateur). */
    public const ECART_NOTABLE = 0.2;

    public function __construct(
        private readonly BilanAnnee $bilan,
        private readonly AutoEvaluationManager $autoEvaluation,
    ) {
    }

    /**
     * @param bool $vueEnseignant true : toutes les évaluations rendues ; false : seulement celles communiquées à l'étudiant
     * @param bool $detail        inclure les éléments (AC / niveaux) de chaque compétence
     *
     * @return array<string, mixed>
     */
    public function pourScolarite(EtudiantScolarite $scolarite, bool $vueEnseignant, bool $detail = false): array
    {
        try {
            $granularite = $this->autoEvaluation->granularitePour($scolarite);
        } catch (UnifolioValidationException $e) {
            return ['disponible' => false, 'message' => $e->getMessage(), 'competences' => [], 'resume' => self::resumeVide()];
        }

        $etudiant = $scolarite->getEtudiant() ?? throw new \LogicException('Scolarité sans étudiant.');
        $elements = $this->bilan->elements($etudiant, $scolarite, $granularite, null, $vueEnseignant);
        $competences = [];
        $traitees = 0;
        $aEvaluer = 0;
        $ecartMax = null;
        foreach ($elements as $rawElement) {
            $el = $this->element($rawElement);
            $c = $el['competence'] ?? null;
            $c = $c === null ? [] : LooseValue::assoc($c);
            $cle = LooseValue::castInt($c['id'] ?? 0);
            $competences[$cle] ??= [
                'id' => $c['id'] ?? null, 'nomCourt' => $c['nomCourt'] ?? 'Autre', 'libelle' => $c['libelle'] ?? null, 'couleur' => $c['couleur'] ?? null,
                'total' => 0, 'couverts' => 0, 'nbTraces' => 0, '_eval' => [], '_auto' => [], 'elements' => [],
            ];

            $traces = LooseValue::row($el['traces'] ?? []);
            $couvertes = array_filter($traces, static fn (mixed $t) => \in_array(LooseValue::assoc($t)['statutValue'] ?? null, self::STATUTS_COUVRANTS, true));
            $couvert = $couvertes !== [];
            $eval = LooseValue::nullableFloat(LooseValue::assoc($el['synthese'] ?? [])['score'] ?? null) ?? $this->moyenne(array_values(array_map(
                static fn (mixed $e) => LooseValue::nullableFloat(LooseValue::assoc($e)['score'] ?? null),
                array_filter(LooseValue::row($el['evaluationsBilan'] ?? []), static fn (mixed $e) => (LooseValue::assoc(LooseValue::assoc($e)['statut'] ?? [])['value'] ?? null) === 'rendue')
            )));
            $auto = $this->scoreAuto($el);
            foreach ($couvertes as $t) {
                $t = LooseValue::assoc($t);
                $rendue = array_filter(LooseValue::row($t['evaluations'] ?? []), static fn (mixed $e) => (LooseValue::assoc(LooseValue::assoc($e)['statut'] ?? [])['value'] ?? null) === 'rendue');
                if ($rendue === []) {
                    ++$aEvaluer;
                }
            }

            $comp = &$competences[$cle];
            ++$comp['total'];
            $comp['couverts'] += $couvert ? 1 : 0;
            $comp['nbTraces'] += \count($couvertes);
            $traitees += \count($couvertes);
            if ($eval !== null) {
                $comp['_eval'][] = $eval;
            }
            if ($auto !== null) {
                $comp['_auto'][] = $auto;
            }
            if ($eval !== null && $auto !== null) {
                $ecartMax = max($ecartMax ?? 0.0, abs(LooseValue::float($eval) - LooseValue::float($auto)));
            }
            if ($detail) {
                $comp['elements'][] = [
                    'type' => $el['type'], 'id' => $el['id'], 'code' => $el['code'], 'ordre' => $el['ordre'], 'libelle' => $el['libelle'],
                    'couvert' => $couvert, 'nbTraces' => \count($couvertes), 'evalScore' => $eval, 'autoScore' => $auto,
                ];
            }
            unset($comp);
        }

        $total = 0;
        $couverts = 0;
        $evals = [];
        $autos = [];
        foreach ($competences as &$comp) {
            $comp['evalScore'] = $this->moyenne($comp['_eval']);
            $comp['autoScore'] = $this->moyenne($comp['_auto']);
            array_push($evals, ...$comp['_eval']);
            array_push($autos, ...$comp['_auto']);
            $total += $comp['total'];
            $couverts += $comp['couverts'];
            unset($comp['_eval'], $comp['_auto']);
            if (!$detail) {
                unset($comp['elements']);
            }
        }
        unset($comp);

        return [
            'disponible' => true,
            'granularite' => ['value' => $granularite->value, 'libelle' => $granularite->getLibelle()],
            'competences' => array_values($competences),
            'resume' => [
                'total' => $total, 'couverts' => $couverts, 'nbTraces' => $traitees, 'aEvaluer' => $aEvaluer,
                'evalScore' => $this->moyenne($evals), 'autoScore' => $this->moyenne($autos), 'ecartMax' => $ecartMax !== null ? round($ecartMax, 4) : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function resumeVide(): array
    {
        return ['total' => 0, 'couverts' => 0, 'nbTraces' => 0, 'aEvaluer' => 0, 'evalScore' => null, 'autoScore' => null, 'ecartMax' => null];
    }

    /** Auto-évaluation du bilan de l'élément si elle est soumise, sinon moyenne de celles de ses traces. */
    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function element(mixed $value): array
    {
        $row = LooseValue::assoc($value);
        $competence = $row['competence'] === null ? null : LooseValue::assoc($row['competence']);
        $traces = [];
        foreach (LooseValue::row($row['traces'] ?? []) as $trace) {
            $traceRow = LooseValue::assoc($trace);
            $evaluations = [];
            foreach (LooseValue::row($traceRow['evaluations'] ?? []) as $evaluation) {
                $evaluationRow = LooseValue::assoc($evaluation);
                $evaluations[] = ['statut' => LooseValue::assoc($evaluationRow['statut'] ?? [])];
            }
            $auto = $traceRow['autoEvaluation'] === null ? null : LooseValue::assoc($traceRow['autoEvaluation']);
            $traces[] = ['statutValue' => $traceRow['statutValue'] ?? null, 'evaluations' => $evaluations, 'autoEvaluation' => $auto];
        }
        $evaluationsBilan = [];
        foreach (LooseValue::row($row['evaluationsBilan'] ?? []) as $evaluation) {
            $evaluationRow = LooseValue::assoc($evaluation);
            $evaluationsBilan[] = ['score' => $evaluationRow['score'] ?? null, 'statut' => LooseValue::assoc($evaluationRow['statut'] ?? [])];
        }
        $bilan = $row['bilan'] === null ? null : LooseValue::assoc($row['bilan']);

        return [
            'bilan' => $bilan,
            'competence' => $competence,
            'traces' => $traces,
            'evaluationsBilan' => $evaluationsBilan,
            'synthese' => LooseValue::assoc($row['synthese'] ?? []),
            'type' => $row['type'] ?? null, 'id' => $row['id'] ?? null, 'code' => $row['code'] ?? null,
            'ordre' => $row['ordre'] ?? null, 'libelle' => $row['libelle'] ?? null,
        ];
    }

    /** @param array<string, mixed> $el */
    private function scoreAuto(array $el): ?float
    {
        $soumise = static function (mixed $a): bool {
            if (!is_array($a)) {
                return false;
            }
            $a = LooseValue::assoc($a);
            return (LooseValue::assoc($a['statut'] ?? [])['value'] ?? null) === 'soumise' && ($a['score'] ?? null) !== null;
        };
        if ($soumise($el['bilan'])) {
            return LooseValue::float(LooseValue::assoc($el['bilan'])['score']);
        }

        return $this->moyenne(array_values(array_map(
            static function (mixed $t) use ($soumise): ?float {
                $trace = LooseValue::assoc($t);

                return $soumise($trace['autoEvaluation'] ?? null) ? LooseValue::float(LooseValue::assoc($trace['autoEvaluation'])['score']) : null;
            },
            LooseValue::row($el['traces'] ?? [])
        )));
    }

    /** @param array<int, float|int|null> $valeurs */
    private function moyenne(array $valeurs): ?float
    {
        $valeurs = array_values(array_filter($valeurs, static fn ($v) => $v !== null));

        return $valeurs === [] ? null : round(array_sum($valeurs) / \count($valeurs), 4);
    }
}
