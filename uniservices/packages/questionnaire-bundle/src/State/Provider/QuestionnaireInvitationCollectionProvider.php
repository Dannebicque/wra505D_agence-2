<?php

declare(strict_types=1);

namespace QuestionnaireBundle\State\Provider;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use QuestionnaireBundle\Repository\Questionnaires\QuestionnaireRepository;
use QuestionnaireBundle\Security\QuestionnaireFollowUpVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Les invitations portent le jeton de réponse, l'adresse et l'historique des relances : la liste
 * n'est servie que pour un questionnaire donné, au personnel qui en assure le suivi.
 *
 * @implements ProviderInterface<object>
 */
final class QuestionnaireInvitationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly CollectionProvider $collectionProvider,
        private readonly QuestionnaireRepository $questionnaireRepository,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /** @return iterable<object> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $filters = is_array($context['filters'] ?? null) ? $context['filters'] : [];
        $questionnaire = $this->questionnaireRepository->findOneBy(['uuid' => self::requestedUuid($filters)]);
        if (!$questionnaire instanceof Questionnaire) {
            throw new BadRequestHttpException('Le paramètre questionnaire est obligatoire.');
        }
        if (!$this->authorizationChecker->isGranted(QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $questionnaire)) {
            throw new AccessDeniedException();
        }

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }

    /**
     * Le filtre accepte l'IRI du questionnaire (`questionnaire`) ou son identifiant (`questionnaire.uuid`).
     *
     * @param array<mixed> $filters
     */
    private static function requestedUuid(array $filters): string
    {
        $value = $filters['questionnaire.uuid'] ?? $filters['questionnaire'] ?? null;
        if (!is_string($value) || preg_match('#^(?:/api/questionnaires/)?([0-9a-fA-F-]{36})$#', $value, $matches) !== 1) {
            throw new BadRequestHttpException('Le paramètre questionnaire est obligatoire.');
        }

        return $matches[1];
    }
}
