<?php

namespace FinanceBundle\Controller;

use App\Service\Pdf\PdfExportOptions;
use App\Service\Pdf\PdfExportService;
use Doctrine\ORM\EntityManagerInterface;
use FinanceBundle\Entity\FinanceBonCommande;
use FinanceBundle\Enum\StatutCommandeEnum;
use FinanceBundle\Security\FinanceVoter;
use FinanceBundle\Services\SignedPdfFiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Formulaire de demande d'achat en PDF ou en HTML, dépôt et lecture du PDF signé. `^/api` est
 * public : chaque route vérifie d'abord le rôle finance, puis le département de la demande.
 */
class FinancePdfController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PdfExportService $pdfExportService,
        private readonly SignedPdfFiles $signedPdfFiles,
        private readonly Environment $twig
    ) {
    }

    #[Route('/api/finance_bon_commandes/{id}/pdf', name: 'api_finance_bon_commande_pdf', methods: ['GET'])]
    public function getBonCommandePdf(int $id, Request $request): Response
    {
        $commande = $this->findCommande($id, FinanceVoter::VIEW);
        if (!$commande instanceof FinanceBonCommande) {
            return new Response('Demande / Bon de commande non trouvé', Response::HTTP_NOT_FOUND);
        }

        $step = $this->step($request);
        $numero = $commande->getNumeroBonCommande();
        $num = $numero !== null && $numero !== '' ? $numero : 'demande_' . $commande->getId();
        $filename = sprintf('demande_achat_%s_%s_etape_%d', $num, date('Ymd'), $step);

        $options = PdfExportOptions::create()
            ->setWithHeader(false)
            ->setWithFooter(false)
            ->setInline(true)
            ->setMargins(0.8, 0.8, 0.8, 0.8);

        return $this->pdfExportService->generateStreamedResponse(
            '@Finance/pdf/formulaire_bon_commande.html.twig',
            [
                'commande' => $commande,
                'step' => $step,
            ],
            $filename,
            $options
        );
    }

    #[Route('/api/finance_bon_commandes/{id}/upload-signed-pdf', name: 'api_finance_bon_commande_upload_signed_pdf', methods: ['POST'])]
    public function uploadSignedPdf(int $id, Request $request): JsonResponse
    {
        $commande = $this->findCommande($id, FinanceVoter::EDIT);
        if (!$commande instanceof FinanceBonCommande) {
            return new JsonResponse(['error' => 'Demande / Bon de commande non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Aucun fichier fourni'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->signedPdfFiles->isPdf($file)) {
            return new JsonResponse(['error' => 'Le fichier doit obligatoirement être un document PDF de 10 Mo au plus'], Response::HTTP_BAD_REQUEST);
        }

        $fileName = $this->signedPdfFiles->store($file, $commande);
        $commande->setPdfFilePath($fileName);

        // Si la commande était en brouillon, elle passe à SOUMIS
        if ($commande->getStatut() === StatutCommandeEnum::BROUILLON) {
            $commande->setStatut(StatutCommandeEnum::SOUMIS);
        }

        if ($commande->getDateSignatureDemandeur() === null) {
            $commande->setDateSignatureDemandeur(new \DateTime());
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Document PDF signé enregistré avec succès',
            'pdfFilePath' => $fileName,
            'statut' => $commande->getStatut()->value,
        ]);
    }

    #[Route('/api/finance_bon_commandes/{id}/download-signed-pdf', name: 'api_finance_bon_commande_download_signed_pdf', methods: ['GET'])]
    public function downloadSignedPdf(int $id): Response
    {
        $commande = $this->findCommande($id, FinanceVoter::VIEW);
        if (!$commande instanceof FinanceBonCommande) {
            return new Response('Demande / Bon de commande non trouvé', Response::HTTP_NOT_FOUND);
        }

        $path = $this->signedPdfFiles->path($commande);
        if ($path === null) {
            return new Response('Aucun document signé n\'est associé à cette demande', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path, Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, sprintf('demande_%d_signee.pdf', $id));

        return $response;
    }

    #[Route('/api/finance_bon_commandes/{id}/html', name: 'api_finance_bon_commande_html', methods: ['GET'])]
    public function getBonCommandeHtml(int $id, Request $request): Response
    {
        $commande = $this->findCommande($id, FinanceVoter::VIEW);
        if (!$commande instanceof FinanceBonCommande) {
            return new Response('Demande / Bon de commande non trouvé', Response::HTTP_NOT_FOUND);
        }

        $html = $this->twig->render('@Finance/pdf/formulaire_bon_commande.html.twig', [
            'commande' => $commande,
            'step' => $this->step($request),
        ]);

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /**
     * Le rôle est vérifié avant la recherche : sans lui, l'existence d'une demande ne se devine pas.
     */
    private function findCommande(int $id, string $attribute): ?FinanceBonCommande
    {
        $this->denyAccessUnlessGranted($attribute);

        $commande = $this->em->getRepository(FinanceBonCommande::class)->find($id);
        if ($commande instanceof FinanceBonCommande) {
            $this->denyAccessUnlessGranted($attribute, $commande);
        }

        return $commande;
    }

    /** Étape de 1 à 5 du formulaire ; hors bornes, le document complet. */
    private function step(Request $request): int
    {
        $step = $request->query->getInt('step', 0);

        return $step < 1 || $step > 5 ? 5 : $step;
    }
}
