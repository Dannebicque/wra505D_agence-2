<?php

namespace UnifolioBundle\Service\Suivi;

use App\Utils\LooseValue;
use UnifolioBundle\Entity\Campagne;
use UnifolioBundle\Service\Campagne\CampagneEtudiants;
use UnifolioBundle\Service\Campagne\CampagneNormalizer;

/**
 * Tableau de bord d'une campagne : matrice étudiants × compétences, indicateurs de promo, alertes par étudiant.
 * Le périmètre (tous les étudiants ou seulement ceux affectés à l'enseignant) est décidé par l'appelant.
 */
class SuiviCampagne
{
    public function __construct(
        private readonly CampagneEtudiants $etudiants,
        private readonly SuiviCalculator $calculateur,
        private readonly CampagneNormalizer $normalizer,
    ) {
    }

    /**
     * @param list<int>|null $etudiantIds null = toute la campagne
     *
     * @return array<string, mixed>
     */
    public function construire(Campagne $campagne, ?array $etudiantIds = null): array
    {
        $maintenant = new \DateTimeImmutable();
        $enRetardGlobal = $campagne->isEvaluationEnRetard($maintenant);
        $debutee = $campagne->getOuvertureLe() <= $maintenant;
        $colonnes = [];
        $lignes = [];
        $totaux = ['total' => 0, 'couverts' => 0, 'nbTraces' => 0, 'aEvaluer' => 0, 'avecDepot' => 0, 'ecartsNotables' => 0, 'sansDepot' => 0];
        $indisponible = null;

        foreach ($this->etudiants->scolarites($campagne) as $id => $scolarite) {
            if ($etudiantIds !== null && !\in_array($id, $etudiantIds, true)) {
                continue;
            }
            $suivi = $this->normaliserSuivi($this->calculateur->pourScolarite($scolarite, true));
            $etudiant = $scolarite->getEtudiant() ?? throw new \LogicException('Scolarité sans étudiant.');
            if (!$suivi['disponible']) {
                $indisponible = $suivi['message'];
                $lignes[] = ['etudiant' => ['id' => $id, 'nom' => $etudiant->getNom(), 'prenom' => $etudiant->getPrenom()], 'cellules' => [], 'resume' => $suivi['resume'], 'alertes' => []];
                continue;
            }

            $cellules = [];
            foreach ($suivi['competences'] as $c) {
                $colonnes[LooseValue::castInt($c['id'])] ??= ['id' => $c['id'], 'nomCourt' => $c['nomCourt'], 'libelle' => $c['libelle'], 'couleur' => $c['couleur'], 'total' => $c['total']];
                $cellules[LooseValue::castInt($c['id'])] = array_intersect_key($c, array_flip(['total', 'couverts', 'nbTraces', 'evalScore', 'autoScore']));
            }
            $r = $suivi['resume'];
            $alertes = [];
            if ($debutee && $r['nbTraces'] === 0) {
                $alertes[] = 'sans_depot';
                ++$totaux['sansDepot'];
            }
            if ($r['aEvaluer'] > 0) {
                $alertes[] = $enRetardGlobal ? 'evaluation_en_retard' : 'a_evaluer';
            }
            if ($r['ecartMax'] !== null && $r['ecartMax'] >= SuiviCalculator::ECART_NOTABLE) {
                $alertes[] = 'ecart_notable';
                ++$totaux['ecartsNotables'];
            }
            $totaux['avecDepot'] += $r['nbTraces'] > 0 ? 1 : 0;
            foreach (['total', 'couverts', 'nbTraces', 'aEvaluer'] as $k) {
                $totaux[$k] += $r[$k];
            }

            $lignes[] = [
                'etudiant' => ['id' => $id, 'nom' => $etudiant->getNom(), 'prenom' => $etudiant->getPrenom()],
                'cellules' => $cellules,
                'resume' => $r,
                'alertes' => $alertes,
            ];
        }

        $n = \count($lignes);

        return [
            'campagne' => $this->normalizer->resume($campagne),
            'disponible' => $indisponible === null || $colonnes !== [],
            'message' => $indisponible,
            'competences' => array_values($colonnes),
            'lignes' => $lignes,
            'indicateurs' => [
                'nbEtudiants' => $n,
                'tauxDepot' => $n > 0 ? round($totaux['avecDepot'] / $n, 4) : null,
                'tauxCouverture' => $totaux['total'] > 0 ? round($totaux['couverts'] / $totaux['total'], 4) : null,
                'sansDepot' => $totaux['sansDepot'],
                'aEvaluer' => $totaux['aEvaluer'],
                'enRetard' => $enRetardGlobal ? $totaux['aEvaluer'] : 0,
                'ecartsNotables' => $totaux['ecartsNotables'],
            ],
        ];
    }

    /** CSV (séparateur « ; », UTF-8 avec BOM pour Excel). Les cellules texte sont neutralisées contre l'injection de formules. */
    /** @param array<string, mixed> $suivi */
    public function csv(array $suivi): string
    {
        $competences = [];
        foreach (LooseValue::row($suivi['competences'] ?? []) as $competence) {
            $c = LooseValue::assoc($competence);
            $competences[] = ['id' => LooseValue::nullableInt($c['id'] ?? null), 'nomCourt' => LooseValue::castString($c['nomCourt'] ?? '')];
        }
        $lignes = [];
        foreach (LooseValue::row($suivi['lignes'] ?? []) as $ligne) {
            $l = LooseValue::assoc($ligne);
            $etudiant = LooseValue::assoc($l['etudiant'] ?? []);
            $resume = LooseValue::assoc($l['resume'] ?? []);
            $cellules = [];
            foreach (LooseValue::row($l['cellules'] ?? []) as $cle => $cellule) {
                $cell = LooseValue::assoc($cellule);
                $cellules[$cle] = ['couverts' => LooseValue::castInt($cell['couverts'] ?? 0), 'total' => LooseValue::castInt($cell['total'] ?? 0), 'evalScore' => LooseValue::nullableFloat($cell['evalScore'] ?? null), 'autoScore' => LooseValue::nullableFloat($cell['autoScore'] ?? null)];
            }
            $lignes[] = ['etudiant' => ['nom' => LooseValue::castString($etudiant['nom'] ?? ''), 'prenom' => LooseValue::castString($etudiant['prenom'] ?? '')], 'cellules' => $cellules, 'resume' => ['nbTraces' => LooseValue::castInt($resume['nbTraces'] ?? 0), 'aEvaluer' => LooseValue::castInt($resume['aEvaluer'] ?? 0)], 'alertes' => array_map(LooseValue::castString(...), LooseValue::row($l['alertes'] ?? []))];
        }
        $pct = static fn (?float $v) => $v === null ? '' : (string) round($v * 100);
        $entete = ['Nom', 'Prénom'];
        foreach ($competences as $c) {
            array_push($entete, $c['nomCourt'].' — couverts', $c['nomCourt'].' — évaluation (%)', $c['nomCourt'].' — auto-évaluation (%)');
        }
        array_push($entete, 'Traces déposées', 'À évaluer', 'Alertes');
        $sortie = [$entete];
        foreach ($lignes as $l) {
            $ligne = [$l['etudiant']['nom'], $l['etudiant']['prenom']];
            foreach ($competences as $c) {
                $cell = $l['cellules'][$c['id'] ?? 0] ?? null;
                array_push($ligne, $cell !== null ? $cell['couverts'].'/'.$cell['total'] : '', $cell !== null ? $pct($cell['evalScore']) : '', $cell !== null ? $pct($cell['autoScore']) : '');
            }
            array_push($ligne, $l['resume']['nbTraces'], $l['resume']['aEvaluer'], implode(', ', $l['alertes']));
            $sortie[] = $ligne;
        }

        $flux = fopen('php://temp', 'r+');
        if ($flux === false) {
            throw new \RuntimeException('Impossible d’ouvrir le flux CSV.');
        }
        fwrite($flux, "\xEF\xBB\xBF");
        foreach ($sortie as $ligne) {
            fputcsv($flux, array_map(self::neutraliser(...), $ligne), ';', '"', '\\');
        }
        rewind($flux);
        $contenu = (string) stream_get_contents($flux);
        fclose($flux);

        return $contenu;
    }

    private static function neutraliser(mixed $valeur): string
    {
        $texte = LooseValue::castString($valeur);

        return $texte !== '' && str_contains("=+-@\t\r", $texte[0]) ? "'".$texte : $texte;
    }

    /**
     * @param array<string, mixed> $suivi
     * @return array{disponible: bool, message: ?string, competences: list<array{id: int|null, nomCourt: string, libelle: ?string, couleur: ?string, total: int, couverts: int, nbTraces: int, evalScore: ?float, autoScore: ?float}>, resume: array{total: int, couverts: int, nbTraces: int, aEvaluer: int, evalScore: ?float, autoScore: ?float, ecartMax: ?float}}
     */
    private function normaliserSuivi(array $suivi): array
    {
        $disponible = LooseValue::bool($suivi['disponible'] ?? false);
        $resume = LooseValue::assoc($suivi['resume'] ?? []);
        $resumeType = [
            'total' => LooseValue::castInt($resume['total'] ?? 0), 'couverts' => LooseValue::castInt($resume['couverts'] ?? 0),
            'nbTraces' => LooseValue::castInt($resume['nbTraces'] ?? 0), 'aEvaluer' => LooseValue::castInt($resume['aEvaluer'] ?? 0),
            'evalScore' => LooseValue::nullableFloat($resume['evalScore'] ?? null), 'autoScore' => LooseValue::nullableFloat($resume['autoScore'] ?? null),
            'ecartMax' => LooseValue::nullableFloat($resume['ecartMax'] ?? null),
        ];
        $competences = [];
        foreach (LooseValue::row($suivi['competences'] ?? []) as $competence) {
            $c = LooseValue::assoc($competence);
            $competences[] = [
                'id' => LooseValue::nullableInt($c['id'] ?? null), 'nomCourt' => LooseValue::castString($c['nomCourt'] ?? ''),
                'libelle' => LooseValue::nullableString($c['libelle'] ?? null), 'couleur' => LooseValue::nullableString($c['couleur'] ?? null),
                'total' => LooseValue::castInt($c['total'] ?? 0), 'couverts' => LooseValue::castInt($c['couverts'] ?? 0),
                'nbTraces' => LooseValue::castInt($c['nbTraces'] ?? 0), 'evalScore' => LooseValue::nullableFloat($c['evalScore'] ?? null),
                'autoScore' => LooseValue::nullableFloat($c['autoScore'] ?? null),
            ];
        }

        return ['disponible' => $disponible, 'message' => LooseValue::nullableString($suivi['message'] ?? null), 'competences' => $competences, 'resume' => $resumeType];
    }
}
