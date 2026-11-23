<?php

namespace QuestionnaireBundle\Command;

use App\Service\Email\EmailService;
use App\Utils\LooseValue;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use QuestionnaireBundle\Repository\Questionnaires\QuestionnaireInvitationRepository;
use QuestionnaireBundle\Repository\Questionnaires\QuestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Relance automatique des participants (questionnaires publiés et ouverts) qui n'ont pas répondu, au plus une fois
 * tous les --days jours. Les liens des e-mails pointent vers URL_FRONTEND.
 */
#[AsCommand(
    name: 'app:questionnaire:send-reminders',
    description: 'Relance les participants des questionnaires ouverts qui n\'ont pas encore répondu',
)]
class SendSurveyRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuestionnaireNotifier $notifier,
        private readonly QuestionnaireRepository $questionnaireRepository,
        private readonly QuestionnaireInvitationRepository $invitationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Nombre minimal de jours depuis le dernier rappel ou la création avant un nouveau rappel',
                '3'
            )
            ->addOption(
                'base-url',
                'b',
                InputOption::VALUE_REQUIRED,
                'URL de base du frontend (ex: http://localhost:3000)',
                'http://localhost:3000'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = LooseValue::castInt($input->getOption('days'));
        $baseUrl = rtrim(LooseValue::castString($input->getOption('base-url')), '/');

        if ($days <= 0) {
            $io->error('L\'option --days doit être un entier strictement supérieur à 0.');
            return Command::INVALID;
        }

        $io->title('Relances Automatiques des Questionnaires');

        // Récupérer les questionnaires actifs et publiés
        $questionnaires = $this->questionnaireRepository->findActivePublished();
        $io->text(sprintf('Trouvé %d questionnaire(s) actif(s) et publié(s).', count($questionnaires)));

        if ($questionnaires === []) {
            $io->success('Aucun questionnaire actif à traiter.');
            return Command::SUCCESS;
        }

        $totalSent = 0;
        $olderThan = new \DateTimeImmutable(sprintf('-%d days', $days));

        foreach ($questionnaires as $q) {
            $io->section(sprintf('Traitement de : %s', $q->getTitle()));

            // Trouver les invitations concernées (PENDING ou STARTED, n'ayant pas reçu de rappel dans les derniers X jours)
            $invitations = $this->invitationRepository->findPendingInvitationsForQuestionnaire($q, $olderThan);
            $io->text(sprintf('  - %d participant(s) à relancer.', count($invitations)));

            foreach ($invitations as $inv) {
                $emailAddress = $inv->getEmail();
                if (null === $emailAddress || trim($emailAddress) === '') {
                    continue;
                }

                $surveyUrl = sprintf('%s/take/%s', $baseUrl, $inv->getToken());

                try {
                    $this->emailService->send(
                        emailKey: 'questionnaire.reminder',
                        to: $emailAddress,
                        context: [
                            'invitation'    => $inv,
                            'questionnaire' => $q,
                            'surveyUrl'     => $surveyUrl,
                            'expiresAt'     => $q->getClosingDate()
                        ]
                    );

                    $inv->incrementRemindersCount();
                    $this->em->persist($inv);
                    $totalSent++;

                    $io->text(sprintf('    * Relance envoyée à : %s', $emailAddress));
                } catch (\Exception $e) {
                    $io->error(sprintf('    * Échec de l\'envoi à %s : %s', $emailAddress, $e->getMessage()));
                }
            }
        }

        if ($totalSent > 0) {
            $this->em->flush();
        }

        $io->success(sprintf('Relances terminées. %d email(s) de rappel envoyé(s).', $totalSent));

        return Command::SUCCESS;
    }
}
