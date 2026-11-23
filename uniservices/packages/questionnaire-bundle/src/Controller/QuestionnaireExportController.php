<?php

namespace QuestionnaireBundle\Controller;

use App\Security\UserTypeVoter;
use App\Service\Pdf\PdfExportOptions;
use App\Service\Pdf\PdfExportService;
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use Doctrine\ORM\EntityManagerInterface;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use QuestionnaireBundle\Security\QuestionnaireFollowUpVoter;
use QuestionnaireBundle\Services\Analytics\QuestionnaireAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[\App\AnneeUniversitaire\ContexteAnnuel('ressource', 'Questionnaire explicitement identifié.', \QuestionnaireBundle\Entity\Questionnaires\Questionnaire::class, 'uuid', 'uuid')]
class QuestionnaireExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuestionnaireAnalyticsService $analyticsService,
        private readonly SpreadsheetRenderer $spreadsheetRenderer,
        private readonly PdfExportService $pdfExportService,
    ) {
    }

    /**
     * Export Excel des statistiques (HtmlToSpreadsheetBundle).
     * ?sections=12,13 limite l'export à ces sections (restitution individuelle) ; un destinataire des résultats n'obtient
     * que les siennes.
     */
    #[Route('/api/questionnaires/{uuid}/export-excel', name: 'api_questionnaire_export_excel', methods: ['GET'])]
    public function excel(string $uuid, Request $request): Response
    {
        [$q, $analytics] = $this->resultats($uuid, $request);

        return $this->spreadsheetRenderer->renderFromTemplate(
            '@Questionnaire/export/analytics.xlsx.twig',
            ['questionnaire' => $q, 'analytics' => $analytics],
            'Statistiques_'.$this->safeName($q).'.xlsx'
        );
    }

    /**
     * Export PDF des statistiques (Gotenberg), éventuellement limité à des sections (?sections=12,13).
     * Les graphiques Chart.js rendus côté client peuvent être transmis en images : {"charts": {"<clé>": "data:image/png;base64,..."}}.
     */
    #[Route('/api/questionnaires/{uuid}/export-pdf', name: 'api_questionnaire_export_pdf', methods: ['POST'])]
    public function pdf(string $uuid, Request $request): Response
    {
        [$q, $analytics] = $this->resultats($uuid, $request);

        $payload = json_decode($request->getContent(), true);
        $charts = [];
        if (is_array($payload) && is_array($payload['charts'] ?? null)) {
            foreach ($payload['charts'] as $key => $image) {
                // N'accepte que des images PNG/JPEG en data URI
                if (is_string($image) && preg_match('#^data:image/(png|jpeg);base64,[A-Za-z0-9+/=]+$#', $image) === 1) {
                    $charts[(string) $key] = $image;
                }
            }
        }

        $options = PdfExportOptions::create()
            ->setWithHeader(false)
            ->setWithFooter(true)
            ->setTitle('Statistiques - ' . $q->getTitle())
            ->setMargins(1.0, 1.2, 0.8, 0.8);

        return $this->pdfExportService->generateStreamedResponse(
            '@Questionnaire/export/analytics.html.twig',
            [
                'questionnaire' => $q,
                'analytics' => $this->analyticsService->getAnalytics($q),
                'charts' => $charts,
            ],
            'Statistiques_' . $this->safeName($q),
            $options
        );
    }

    private function findQuestionnaire(string $uuid): Questionnaire
    {
        $this->denyAccessUnlessGranted(UserTypeVoter::IS_PERSONNEL);
        $q = $this->em->getRepository(Questionnaire::class)->findOneBy(['uuid' => $uuid]);
        if (!$q instanceof Questionnaire) {
            throw new NotFoundHttpException('Questionnaire not found');
        }
        $this->denyAccessUnlessGranted(QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $q);

        return $q;
    }

    private function safeName(Questionnaire $q): string
    {
        $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $q->getTitle() ?? '');

        return $name === null || $name === '' ? 'questionnaire' : $name;
    }
}
