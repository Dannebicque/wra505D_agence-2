<?php

namespace QuestionnaireBundle\Services\Dashboard\Provider;

use App\Domain\Dashboard\DashboardContext;
use App\Domain\Dashboard\WidgetDataProviderInterface;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use Doctrine\ORM\QueryBuilder;
use QuestionnaireBundle\Entity\Questionnaires\QuestionnaireInvitation;
use QuestionnaireBundle\Enum\QuestInvitationStatusEnum;
use QuestionnaireBundle\Enum\QuestStatutEnum;
use QuestionnaireBundle\Repository\Questionnaires\QuestionnaireInvitationRepository;
use QuestionnaireBundle\Repository\Questionnaires\QuestionnaireRepository;
use QuestionnaireBundle\Security\QuestionnaireAccess;
use QuestionnaireBundle\State\Provider\Questionnaire\Runtime\MesInvitationsProvider;

/**
 * Données des widgets du portail, limitées à ce que la personne peut voir :
 *  - questionnaire.pending : SES questionnaires à remplir (invitations ouvertes et non envoyées) ;
 *  - questionnaire.stats : questionnaires qu'elle gère (départements gérés et questionnaires dont elle est l'auteur) ;
 *  - questionnaire.last_answers : dernières réponses envoyées à ces questionnaires (date et titre, jamais l'identité).
 */
class QuestionnaireWidgetDataProvider implements WidgetDataProviderInterface
{
    public function __construct(
        private readonly QuestionnaireRepository $questionnaires,
        private readonly QuestionnaireInvitationRepository $invitations,
        private readonly QuestionnaireAccess $access,
    ) {
    }

    public function supports(string $code): bool
    {
        return str_starts_with($code, 'questionnaire.');
    }

    /** @return array<string, mixed> */
    public function getData(string $code, Personnel|Etudiant $user, ?DashboardContext $context = null): array
    {
        return match ($code) {
            'questionnaire.pending' => [
                // List the 5 most recently published questionnaires
                'items' => array_map(
                    fn ($q) => $q->getTitle(),
                    $this->questionnaireRepository->findBy(
                        ['status' => QuestStatutEnum::PUBLISHED],
                        ['publishedAt' => 'DESC'],
                        5
                    )
                ),
            ],
            'questionnaire.stats' => [
                'total' => $this->questionnaireRepository->count([]),
                'published' => $this->questionnaireRepository->count(['status' => QuestStatutEnum::PUBLISHED]),
                'draft' => $this->questionnaireRepository->count(['status' => QuestStatutEnum::DRAFT]),
                'closed' => $this->questionnaireRepository->count(['status' => QuestStatutEnum::CLOSED]),
            ],
            'questionnaire.last_answers' => [
                // List the 5 most recently created questionnaires
                'items' => array_map(
                    fn ($q) => $q->getTitle() . ' (' . ($q->getCreatedAt()?->format('d/m H:i') ?? 'N/A') . ')',
                    $this->questionnaireRepository->findBy(
                        [],
                        ['createdAt' => 'DESC'],
                        5
                    )
                ),
            ],
            default => [],
        };
    }
}
