<?php

namespace QuestionnaireBundle\State\Provider\Questionnaire\Publish;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use QuestionnaireBundle\Domain\Questionnaire\Publish\PublishQuestionnaireService;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use QuestionnaireBundle\Security\QuestionnaireResolver;
use QuestionnaireBundle\Security\QuestionnaireVoter;
use QuestionnaireBundle\ApiDto\Questionnaire\Publish\PublishInputDto;
use QuestionnaireBundle\ApiDto\Questionnaire\Publish\PublishOutputDto;

/**
 * @implements ProcessorInterface<PublishInputDto, PublishOutputDto>
 */
final class PublishProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly QuestionnaireResolver       $questionnaires,
        private readonly PublishQuestionnaireService $service
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublishOutputDto
    {
        /** @var PublishInputDto $data */
        $q = $this->questionnaires->charger($uriVariables['questionnaireUuid'] ?? '', QuestionnaireVoter::CAN_PUBLISH_QUESTIONNAIRE);

        $result = $this->service->publish($q, $data->recipients);

        return new PublishOutputDto(
            questionnaireUuid: (string) $q->getUuid(),
            status: $q->getStatus() ?? throw new \LogicException('Le questionnaire publié n\'a pas de statut.'),
            publishedAt: $result['publishedAt'],
            sectionsCount: $result['sectionsCount'],
            invitationsCount: $result['invitationsCount'],
            emailsCount: $result['emailsCount']
        );
    }
}
