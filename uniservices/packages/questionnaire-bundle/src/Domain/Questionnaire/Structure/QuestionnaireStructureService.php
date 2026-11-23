<?php

namespace QuestionnaireBundle\Domain\Questionnaire\Structure;

use App\Utils\LooseValue;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireSection;
use QuestionnaireBundle\Enum\QuestTypeSectionEnum;

final class QuestionnaireStructureService
{
    /**
     * Plan des sections réelles, dans l'ordre de l'éditeur : une section normale donne une section ; une section
     * configurable donne une section par élément choisi (matière, ressource…), titrée d'après son patron.
     *
     * @return list<array{sectionTemplate: QuestionnaireSection, title: string, repeatItemType: ?string, repeatItemId: ?string, sortOrder: int, enseignementId: ?int, personnelId: ?int}>
     */
    public function buildPlan(Questionnaire $q): array
    {
        $sections = $q->getSections()->toArray();
        usort($sections, static fn (QuestionnaireSection $a, QuestionnaireSection $b) => $a->getSortOrder() <=> $b->getSortOrder());

        $plan = [];
        $ordre = 1;
        foreach ($sections as $st) {
            if ($st->getTypeSection() !== QuestTypeSectionEnum::configurable) {
                $plan[] = ['sectionTemplate' => $st, 'title' => (string) $st->getTitle(), 'repeatItemType' => null, 'repeatItemId' => null, 'sortOrder' => $ordre++, 'enseignementId' => null, 'personnelId' => null];
                continue;
            }

            // Les options ont des valeurs par défaut vides : chaîne vide (ou « 0 ») plutôt que « ?? ».
            $opts = $st->getOpt();
            $sourceType = LooseValue::castString($opts['sourceType'] ?? null);
            $type = $sourceType !== '' && $sourceType !== '0' ? $sourceType : 'matiere';
            $titleTemplate = LooseValue::castString($opts['titleTemplate'] ?? null);
            $patron = $titleTemplate !== '' && $titleTemplate !== '0' ? $titleTemplate : 'Évaluation de {element}';
            foreach (LooseValue::row($opts['elements'] ?? []) as $element) {
                if (!\is_array($element)) {
                    continue;
                }
                $plan[] = [
                    'sectionTemplate' => $st,
                    'title' => str_replace('{element}', LooseValue::castString($element['name'] ?? ''), $patron),
                    'repeatItemType' => $type,
                    'repeatItemId' => isset($element['id']) ? LooseValue::castString($element['id']) : null,
                    'sortOrder' => $ordre++,
                    // Enseignement évalué et, pour une ligne de prévisionnel, l'intervenant (renseignés par l'éditeur).
                    'enseignementId' => is_numeric($element['enseignementId'] ?? null) ? (int) $element['enseignementId'] : null,
                    'personnelId' => is_numeric($element['personnelId'] ?? null) ? (int) $element['personnelId'] : null,
                ];
            }
        }

        return $plan;
    }
}
