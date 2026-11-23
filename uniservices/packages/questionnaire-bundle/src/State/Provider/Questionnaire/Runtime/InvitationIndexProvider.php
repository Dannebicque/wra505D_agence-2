<?php

namespace QuestionnaireBundle\State\Provider\Questionnaire\Runtime;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\InvitationIndexDto;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\SectionIndexDto;
use QuestionnaireBundle\Domain\Questionnaire\Runtime\InvitationRuntime;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireSectionInstance;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sommaire d'un questionnaire pour le répondant (par le jeton de l'invitation).
 *
 * @implements ProviderInterface<InvitationIndexDto>
 */
final class InvitationIndexProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvitationRuntime $runtime,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InvitationIndexDto
    {
        $invitation = $this->runtime->invitation($uriVariables['token'] ?? '');
        $questionnaire = $invitation->getQuestionnaire();
        if (!$questionnaire instanceof Questionnaire) {
            throw new NotFoundHttpException('Invitation introuvable.');
        }

        // Ouvrir le questionnaire ne compte comme « commencé » que s'il est ouvert aux réponses.
        if ($questionnaire->estOuvert() && !$invitation->isSubmitted() && $invitation->getStartedAt() === null) {
            $invitation->markStarted();
            $this->em->flush();
        }

        $sections = [];
        foreach ($this->em->getRepository(QuestionnaireSectionInstance::class)->findBy(['questionnaire' => $questionnaire], ['sortOrder' => 'ASC']) as $s) {
            $sections[] = new SectionIndexDto(
                publishedSectionInstanceId: (int) $s->getId(),
                title: (string) $s->getTitleSnapshot(),
                questionCount: $s->getSection()?->getQuestions()->count() ?? 0,
                order: (int) $s->getSortOrder(),
                repeatItemType: $s->getRepeatSectionItemType(),
                repeatItemId: $s->getRepeatSectionItemId(),
            );
        }

        $status = $invitation->getStatus();
        if ($status === null) {
            throw new \LogicException('L\'invitation n\'a pas de statut.');
        }

        return new InvitationIndexDto(
            questionnaireTitle: (string) $questionnaire->getTitle(),
            invitationStatus: $status->value,
            startedAt: $invitation->getStartedAt(),
            modifiable: $this->runtime->estModifiable($invitation),
            submittedAt: $invitation->getSubmittedAt(),
            sections: $sections,
            description: $questionnaire->getDescription(),
            estimatedTime: $questionnaire->getEstimatedTime(),
            startText: $questionnaire->getStartText(),
            endText: $questionnaire->getEndText(),
        );
    }
}
