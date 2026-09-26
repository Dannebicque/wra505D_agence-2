<?php

namespace QuestionnaireBundle\Domain\Questionnaire\Mapping;

use App\Utils\LooseValue;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireQuestion;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\ChoiceDto;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\QuestionRuntimeDto;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\ScaleDto;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\VisibilityRuleDto;
use QuestionnaireBundle\Enum\QuestTypeQuestionEnum;

final class QuestionRuntimeMapper
{
    /** @param iterable<object>|null $allQuestionsInContext */
    public function map(QuestionnaireQuestion $q, mixed $answerValue, ?iterable $allQuestionsInContext = null): QuestionRuntimeDto
    {
        $config = $q->getChoices();

        $choices = null;
        if (in_array($q->getTypeQuestion(), [QuestTypeQuestionEnum::MultipleChoice, QuestTypeQuestionEnum::SingleChoice], true)) {
            $choices = [];
            foreach (LooseValue::rows($config ?? []) as $c) {
                $choices[] = new ChoiceDto(LooseValue::castString($c['id']), LooseValue::castString($c['text']), LooseValue::castString($c['value']));
            }
        }

        $scale = null;
        if ($q->getTypeQuestion() === QuestTypeQuestionEnum::Scale) {
            $scale = new ScaleDto(
                min: LooseValue::castInt($config['min'] ?? 1),
                max: LooseValue::castInt($config['max'] ?? 5),
                minLabel: LooseValue::nullableString($config['minLabel'] ?? null),
                maxLabel: LooseValue::nullableString($config['maxLabel'] ?? null),
            );
        }

        $visibility = null;
        $qId = (string) $q->getId();
        $qUuid = $q->getUuid() ? (string) $q->getUuid() : null;

        $searchQuestions = [];
        if ($allQuestionsInContext !== null) {
            foreach ($allQuestionsInContext as $item) {
                if ($item instanceof QuestionnaireQuestion) {
                    $searchQuestions[] = $item;
                }
            }
        }
        if (empty($searchQuestions)) {
            $searchQuestions = [$q];
        }

        $targetedRule = null;
        foreach ($searchQuestions as $sq) {
            $rules = $sq->getConditionalRules();
            if (empty($rules)) {
                continue;
            }

            $ruleList = isset($rules[0]) && is_array($rules[0]) ? $rules : [$rules];
            foreach ($ruleList as $r) {
                if (!is_array($r)) {
                    continue;
                }

                $targetIds = $r['targetQuestionIds'] ?? [];
                $isTargeted = false;

                if (!empty($targetIds) && is_array($targetIds)) {
                    foreach ($targetIds as $tid) {
                        $tidStr = LooseValue::castString($tid);
                        if ($tidStr === $qId || ($qUuid !== null && $tidStr === $qUuid)) {
                            $isTargeted = true;
                            break;
                        }
                    }
                } else {
                    $dep = LooseValue::castString($r['dependsOnQuestionId'] ?? $r['dependsOn'] ?? '');
                    if ($dep !== '' && $dep !== $qId && ($qUuid === null || $dep !== $qUuid) && (string)$sq->getId() === $qId) {
                        $isTargeted = true;
                    }
                }

                if ($isTargeted) {
                    $targetedRule = $r;
                    break 2;
                }
            }
        }

        if ($targetedRule !== null) {
            $conditions = [];
            $logicalOperator = LooseValue::castString($targetedRule['logicalOperator'] ?? 'AND');
            $action = LooseValue::castString($targetedRule['action'] ?? 'show');

            if (!empty($targetedRule['conditions']) && is_array($targetedRule['conditions'])) {
                foreach ($targetedRule['conditions'] as $cond) {
                    if (!is_array($cond)) {
                        continue;
                    }
                    $dep = $cond['dependsOnQuestionId'] ?? $cond['dependsOn'] ?? null;
                    if ($dep !== null) {
                        $conditions[] = [
                            'dependsOnQuestionId' => is_numeric($dep) ? LooseValue::castInt($dep) : LooseValue::castString($dep),
                            'operator' => LooseValue::castString($cond['operator'] ?? ''),
                            'value' => $cond['value'] ?? null,
                        ];
                    }
                }
            }

            // Fallback for simple condition rules
            if (empty($conditions)) {
                $dep = $targetedRule['dependsOnQuestionId'] ?? $targetedRule['dependsOn'] ?? null;
                $operator = $targetedRule['operator'] ?? null;
                if ($dep !== null && $operator !== null) {
                    $conditions[] = [
                        'dependsOnQuestionId' => is_numeric($dep) ? LooseValue::castInt($dep) : LooseValue::castString($dep),
                        'operator' => LooseValue::castString($operator),
                        'value' => $targetedRule['value'] ?? null,
                    ];
                }
            }

            if (!empty($conditions)) {
                $firstCond = $conditions[0];
                $visibility = new VisibilityRuleDto(
                    dependsOnQuestionId: $firstCond['dependsOnQuestionId'],
                    operator: $firstCond['operator'],
                    value: $firstCond['value'],
                    action: $action,
                    logicalOperator: $logicalOperator,
                    conditions: $conditions
                );
            }
        }

        $typeQuestion = $q->getTypeQuestion();
        $label = $q->getLabel();
        $required = $q->isObligatoire();
        if ($typeQuestion === null || $label === null || $required === null) {
            throw new \LogicException('Question runtime fields are required');
        }

        return new QuestionRuntimeDto(
            questionId: (int) $q->getId(),
            typeQuestion: $typeQuestion,
            label: $label,
            required: $required,
            answer: $answerValue,
            choices: $choices,
            scale: $scale,
            visibility: $visibility,
        );
    }
}
