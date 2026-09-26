<?php

namespace QuestionnaireBundle\State\Provider\Questionnaire\Runtime;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use QuestionnaireBundle\Domain\Questionnaire\Mapping\QuestionRuntimeMapper;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireAnswer;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireInvitation;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireSectionInstance;
use Doctrine\ORM\EntityManagerInterface;
use QuestionnaireBundle\ApiDto\Questionnaire\Runtime\SectionRuntimeDto;

/** @implements ProviderInterface<SectionRuntimeDto> */
final class InvitationSectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private QuestionRuntimeMapper $mapper
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SectionRuntimeDto
    {
        $token = (string) $uriVariables['token'];
        $id = (int) $uriVariables['id'];

        $inv = $this->em->getRepository(QuestionnaireInvitation::class)->findOneBy(['token' => $token]);
        if (!$inv) {
            throw new \RuntimeException('Invitation not found');
        }

        $psi = $this->em->getRepository(QuestionnaireSectionInstance::class)->find($id);
        $questionnaire = $inv->getQuestionnaire();
        if (!$psi || $questionnaire === null || $psi->getQuestionnaire()?->getId() !== $questionnaire->getId()) {
            throw new \RuntimeException('Section not found');
        }
        $section = $psi->getSection();
        $title = $psi->getTitleSnapshot();
        $publishedSectionInstanceId = $psi->getId();
        if ($section === null || $title === null || $publishedSectionInstanceId === null) {
            throw new \LogicException('Section instance fields are required');
        }

        // Charger answers existantes pour cette section
        $answers = $this->em->getRepository(QuestionnaireAnswer::class)->findBy([
            'invitation' => $inv,
            'section' => $psi,
        ]);

        $answersByQid = [];
        foreach ($answers as $a) {
            $question = $a->getQuestion();
            if ($question === null) {
                throw new \LogicException('Answer question is required');
            }
            $answersByQid[$question->getId()] = $a->getValue();
        }

        $allQuestions = iterator_to_array($section->getQuestions());
        $questions = [];
        foreach ($section->getQuestions() as $qt) {
            $questions[] = $this->mapper->map($qt, $answersByQid[$qt->getId()] ?? null, $allQuestions);
        }

        return new SectionRuntimeDto(
            questionnaireTitle: $questionnaire->getTitle() ?? throw new \LogicException('Questionnaire title is required'),
            publishedSectionInstanceId: $publishedSectionInstanceId,
            title: $title,
            repeatItemType: $psi->getRepeatSectionItemType(),
            repeatItemId: $psi->getRepeatSectionItemId() ? (string) $psi->getRepeatSectionItemId() : null,
            questions: $questions
        );
    }
}
