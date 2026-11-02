<?php

declare(strict_types=1);

namespace IntranetBundle\Controller\Export;

use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructureSemestre;
use App\Repository\Structure\StructureGroupeRepository;
use App\Repository\Structure\StructureSemestreRepository;
use App\Security\SemestreListingVoter;
use App\Utils\LooseValue;
use IntranetBundle\Service\Export\ListingEtudiantExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Listings d'émargement, trombinoscopes et exports personnalisés des étudiants d'un semestre.
 */
#[Route('/api/intranet/export')]
class ListingEtudiantExportController extends AbstractController
{
    public function __construct(
        private readonly ListingEtudiantExportService $exportService,
        private readonly StructureSemestreRepository $semestreRepository,
        private readonly StructureGroupeRepository $groupeRepository,
    ) {
    }

    /**
     * Export listing (feuille d'émargement) for a single group in PDF, Excel, or CSV.
     */
    #[Route('/listing/groupe/{groupeId}', name: 'api_intranet_export_listing_groupe', methods: ['GET'])]
    public function exportGroupe(int $groupeId, Request $request): Response
    {
        $groupe = $this->groupeRepository->find($groupeId);
        if (!$groupe instanceof StructureGroupe) {
            return new JsonResponse(['error' => 'Groupe introuvable'], Response::HTTP_NOT_FOUND);
        }

        $semestre = $groupe->getSemestres()->first();
        $semestreIdParam = $request->query->getInt('semestre');
        if ($semestreIdParam > 0) {
            // Seul un semestre du groupe est accepté : sinon le droit sur un semestre ouvrirait
            // le groupe d'un autre département.
            $semestre = $groupe->getSemestres()->findFirst(
                static fn (int $key, StructureSemestre $candidat): bool => $candidat->getId() === $semestreIdParam
            ) ?? $semestre;
        }

        if (!$semestre instanceof StructureSemestre) {
            return new JsonResponse(['error' => 'Aucun semestre associé à ce groupe'], Response::HTTP_BAD_REQUEST);
        }

        $this->denyAccessUnlessGranted(SemestreListingVoter::CAN_EXPORT_LISTING, $semestre);

        return $this->exportService->exportListing($semestre, [$groupe], $request->query->getString('format', 'pdf'));
    }

    /**
     * Export listing for an entire semester (or filtered by group type) in PDF, Excel, or CSV.
     */
    #[Route('/listing/semestre/{semestreId}', name: 'api_intranet_export_listing_semestre', methods: ['GET'])]
    public function exportSemestre(int $semestreId, Request $request): Response
    {
        $semestre = $this->semestreRepository->find($semestreId);
        if (!$semestre instanceof StructureSemestre) {
            return new JsonResponse(['error' => 'Semestre introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(SemestreListingVoter::CAN_EXPORT_LISTING, $semestre);

        $groupes = $this->exportService->getGroupesForExport($semestre, $this->groupeIdParam($request), $this->typeParam($request));
        if ($groupes === []) {
            return new JsonResponse(['error' => 'Aucun groupe trouvé pour les critères demandés'], Response::HTTP_NOT_FOUND);
        }

        return $this->exportService->exportListing($semestre, $groupes, $request->query->getString('format', 'pdf'));
    }

    /**
     * Export photo trombinoscope for a semester / group in PDF via Gotenberg.
     */
    #[Route('/trombinoscope/semestre/{semestreId}', name: 'api_intranet_export_trombinoscope_semestre', methods: ['GET'])]
    public function exportTrombinoscope(int $semestreId, Request $request): Response
    {
        $semestre = $this->semestreRepository->find($semestreId);
        if (!$semestre instanceof StructureSemestre) {
            return new JsonResponse(['error' => 'Semestre introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(SemestreListingVoter::CAN_EXPORT_LISTING, $semestre);

        $groupes = $this->exportService->getGroupesForExport($semestre, $this->groupeIdParam($request), $this->typeParam($request));
        if ($groupes === []) {
            return new JsonResponse(['error' => 'Aucun groupe trouvé pour les critères demandés'], Response::HTTP_NOT_FOUND);
        }

        return $this->exportService->exportTrombinoscope($semestre, $groupes);
    }

    /**
     * Custom export: choose columns and format.
     */
    #[Route('/personnalise', name: 'api_intranet_export_personnalise', methods: ['POST'])]
    public function exportPersonnalise(Request $request): Response
    {
        $decoded = json_decode($request->getContent(), true);
        $payload = is_array($decoded) ? $decoded : [];

        $semestre = $this->semestreRepository->find(LooseValue::castInt($payload['semestreId'] ?? 0));
        if (!$semestre instanceof StructureSemestre) {
            return new JsonResponse(['error' => 'Semestre introuvable'], Response::HTTP_BAD_REQUEST);
        }

        $this->denyAccessUnlessGranted(SemestreListingVoter::CAN_EXPORT_LISTING, $semestre);

        $groupeId = LooseValue::castInt($payload['groupeId'] ?? 0);
        $type = LooseValue::truthyString($payload['type'] ?? null);
        $format = LooseValue::castString($payload['format'] ?? 'xlsx');
        $champs = ['numEtudiant', 'nom', 'prenom', 'mailUniv'];
        if (isset($payload['champs'])) {
            if (!is_array($payload['champs'])) {
                return new JsonResponse(['error' => 'champs doit être une liste'], Response::HTTP_BAD_REQUEST);
            }
            $champs = array_values(array_filter($payload['champs'], is_string(...)));
        }

        $groupes = $this->exportService->getGroupesForExport($semestre, $groupeId > 0 ? $groupeId : null, $type);
        if ($groupes === []) {
            return new JsonResponse(['error' => 'Aucun groupe trouvé'], Response::HTTP_NOT_FOUND);
        }

        return $this->exportService->exportPersonnalise($semestre, $groupes, $champs, $format);
    }

    private function groupeIdParam(Request $request): ?int
    {
        $groupeId = $request->query->getInt('groupeId');

        return $groupeId > 0 ? $groupeId : null;
    }

    private function typeParam(Request $request): ?string
    {
        $type = $request->query->getString('type');

        return $type !== '' ? $type : null;
    }
}
