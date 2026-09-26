<?php

namespace QuestionnaireBundle\State\Provider\Questionnaire\Publish;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use QuestionnaireBundle\Domain\Questionnaire\Publish\PublishQuestionnaireService;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use Doctrine\ORM\EntityManagerInterface;
use QuestionnaireBundle\ApiDto\Questionnaire\Publish\PublishInputDto;
use QuestionnaireBundle\ApiDto\Questionnaire\Publish\PublishOutputDto;

final class PublishProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly PublishQuestionnaireService $service
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PublishOutputDto
    {
        /** @var PublishInputDto $data */
        $id = $uriVariables['questionnaireUuid'];

        $q = $this->em->getRepository(Questionnaire::class)->findOneBy(['uuid' => $id]);
        if (!$q) {
            throw new \RuntimeException('Questionnaire not found');
        }

        $result = $this->service->publish($q, $data->recipients);
        $status = $q->getStatus();
        if ($status === null) {
            throw new \LogicException('Questionnaire status is required');
        }

        return new PublishOutputDto(
            questionnaireUuid: $q->getUuidString(),
            status: $status,
            publishedAt: $result['publishedAt'],
            sectionsCount: $result['sectionsCount'],
            invitationsCount: $result['invitationsCount']
        );
    }
}
