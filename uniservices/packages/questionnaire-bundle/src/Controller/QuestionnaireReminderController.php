<?php

namespace QuestionnaireBundle\Controller;

use App\Security\UserTypeVoter;
use App\Service\Email\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use QuestionnaireBundle\Repository\Questionnaires\QuestionnaireInvitationRepository;
use QuestionnaireBundle\Repository\Questionnaires\QuestionnaireRepository;
use QuestionnaireBundle\Security\QuestionnaireFollowUpVoter;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Relance manuelle des participants d'un questionnaire, avec historique sur chaque invitation.
 */
class QuestionnaireReminderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmailService $emailService,
        private readonly QuestionnaireRepository $questionnaireRepository,
        private readonly QuestionnaireInvitationRepository $invitationRepository,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/api/questionnaires/{uuid}/reminders', name: 'api_questionnaire_send_reminders', methods: ['POST'])]
    public function sendReminders(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserTypeVoter::IS_PERSONNEL);
        $questionnaire = $this->questionnaireRepository->findOneBy(['uuid' => $uuid]);
        if (!$questionnaire instanceof Questionnaire) {
            throw new NotFoundHttpException('Questionnaire introuvable.');
        }
        $this->denyAccessUnlessGranted(QuestionnaireFollowUpVoter::CAN_REMIND_QUESTIONNAIRE, $questionnaire);

        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];
        $tokens = is_array($data['tokens'] ?? null) ? array_values(array_filter($data['tokens'], 'is_string')) : [];
        $subject = is_string($data['subject'] ?? null) ? trim($data['subject']) : '';
        $message = is_string($data['message'] ?? null) ? trim($data['message']) : '';

        if ($tokens === []) {
            return new JsonResponse([
                'error' => 'Aucun participant spécifié.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($subject === '') {
            $subject = sprintf('Rappel : questionnaire "%s" en attente', $questionnaire->getTitle());
        }

        $invitations = $this->invitationRepository->findBy([
            'questionnaire' => $questionnaire,
            'token' => $tokens,
        ]);

        $type = count($invitations) > 1 ? 'bulk' : 'manual';
        $sentCount = 0;
        $baseUrl = $request->getSchemeAndHttpHost();

        $updatedInvitations = [];

        foreach ($invitations as $invitation) {
            if ($invitation->isSubmitted()) {
                continue;
            }

            $invitation->addReminder(
                subject: $subject,
                message: $message !== '' ? $message : null,
                type: $type
            );

            $email = $invitation->getEmail();
            if ($email !== null && trim($email) !== '') {
                $surveyUrl = sprintf('%s/take/%s', $baseUrl, $invitation->getToken());
                try {
                    $this->emailService->send(
                        emailKey: 'questionnaire.reminder',
                        to: $email,
                        context: [
                            'invitation'    => $invitation,
                            'questionnaire' => $questionnaire,
                            'surveyUrl'     => $surveyUrl,
                            'expiresAt'     => $questionnaire->getClosingDate(),
                            'customSubject' => $subject,
                            'customMessage' => $message,
                        ],
                        departement: $questionnaire->getDepartement(),
                    );
                } catch (\Throwable $e) {
                    $this->logger?->warning(sprintf('Échec envoi email relance questionnaire à %s: %s', $email, $e->getMessage()));
                }
            }

            $sentCount++;

            $updatedInvitations[] = [
                'token' => $invitation->getToken(),
                'remindedAt' => $invitation->getRemindedAt()?->format(\DateTimeInterface::ATOM),
                'remindersCount' => $invitation->getRemindersCount(),
                'remindersHistory' => $invitation->getRemindersHistory(),
            ];
        }

        if ($sentCount > 0) {
            $this->em->flush();
        }

        return new JsonResponse([
            'success' => true,
            'sentCount' => $sentCount,
            'invitations' => $updatedInvitations,
        ]);
    }
}
