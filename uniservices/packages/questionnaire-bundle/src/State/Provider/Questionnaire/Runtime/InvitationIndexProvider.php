<?php

namespace QuestionnaireBundle\State\Provider\Questionnaire\Runtime;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireInvitation;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireSectionInstance;
use Doctrine\ORM\EntityManagerInterface;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\InvitationIndexDto;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\SectionIndexDto;

final class InvitationIndexProvider implements ProviderInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InvitationIndexDto
    {
        $token = (string) $uriVariables['token'];

        $inv = $this->em->getRepository(QuestionnaireInvitation::class)->findOneBy(['token' => $token]);
        if (!$inv) {
            throw new \RuntimeException('Invitation not found');
        }

        $inv->markStarted();
        $this->em->flush();

        $q = $inv->getQuestionnaire();
        $status = $inv->getStatus();
        if ($q === null || $status === null) {
            throw new \LogicException('Invitation fields are required');
        }

        $sections = $this->em->getRepository(QuestionnaireSectionInstance::class)->findBy(
            ['questionnaire' => $q],
            ['sortOrder' => 'ASC']
        );

        $out = [];
        foreach ($sections as $s) {
            $section = $s->getSection();
            $title = $s->getTitleSnapshot();
            $order = $s->getSortOrder();
            if ($section === null || $title === null || $order === null) {
                throw new \LogicException('Section instance fields are required');
            }
            $qtCount = $section->getQuestions()->count();
            $out[] = new SectionIndexDto(
                publishedSectionInstanceId: (int) $s->getId(),
                title: $title,
                questionCount: $qtCount,
                order: $order,
                repeatItemType: $s->getRepeatSectionItemType(),
                repeatItemId: $s->getRepeatSectionItemId() ? (string) $s->getRepeatSectionItemId() : null
            );
        }

        $questionnaireTitle = $q->getTitle();
        if ($questionnaireTitle === null) {
            throw new \LogicException('Questionnaire title is required');
        }

        return new InvitationIndexDto(
            questionnaireTitle: $questionnaireTitle,
            invitationStatus: $status->value,
            startedAt: $inv->getStartedAt(),
            submittedAt: $inv->getSubmittedAt(),
            sections: $out
        );
    }
}
