<?php

namespace App\Controller\Etudiant;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Security\PostVoter;
use App\Security\UserEffectivePermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EtudiantApplicationsController extends AbstractController
{
    private const ACTIONS = ['add', 'remove', 'set'];

    public function __construct(
        private readonly UserEffectivePermissionService $permissionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Grants or removes applications for students of the staff member's active department only,
     * whatever department the payload names.
     */
    #[Route('/api/etudiants/bulk-applications', name: 'app_etudiants_bulk_applications', methods: ['POST'])]
    #[IsGranted(PostVoter::CAN_EDIT_ETUDIANT_SCOLARITE)]
    public function updateBulkApplications(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $departement = $user instanceof Personnel
            ? $this->permissionService->getActiveDepartementPersonnel($user)?->getDepartement()
            : null;
        if (!$departement instanceof StructureDepartement) {
            return $this->json(['message' => 'Aucun département actif.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $action = $data['action'] ?? 'set';
        $applications = $data['applications'] ?? [];
        $etudiantIds = $data['etudiantIds'] ?? [];
        if (!in_array($action, self::ACTIONS, true) || !is_array($applications) || !is_array($etudiantIds)) {
            return $this->json(['message' => 'Requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $inputApps = [];
        foreach ($applications as $application) {
            $code = is_string($application) ? strtolower(trim($application)) : '';
            if (1 !== preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $code)) {
                return $this->json(['message' => 'Application inconnue.'], Response::HTTP_BAD_REQUEST);
            }
            $inputApps[$code] = true;
        }
        $inputApps = array_keys($inputApps);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT e')
            ->from(Etudiant::class, 'e')
            ->join('e.scolarites', 'es')
            ->where('es.actif = true')
            ->andWhere('es.departement = :departement')
            ->setParameter('departement', $departement);

        if ([] !== $etudiantIds) {
            $ids = array_values(array_filter($etudiantIds, is_int(...)));
            if (count($ids) !== count($etudiantIds)) {
                return $this->json(['message' => 'Identifiants d\'étudiants invalides.'], Response::HTTP_BAD_REQUEST);
            }
            $qb->andWhere('e.id IN (:ids)')->setParameter('ids', $ids);
        } elseif (is_int($data['anneeId'] ?? null)) {
            $qb->join('es.scolariteSemestre', 'ss')
                ->join('ss.semestre', 's')
                ->andWhere('IDENTITY(s.annee) = :anneeId')
                ->setParameter('anneeId', $data['anneeId']);
            if (is_int($data['anneeUniversitaireId'] ?? null)) {
                $qb->andWhere('IDENTITY(es.anneeUniversitaire) = :auId')
                    ->setParameter('auId', $data['anneeUniversitaireId']);
            }
        } else {
            return $this->json(['message' => 'Aucun étudiant ou année spécifié.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var list<Etudiant> $etudiants */
        $etudiants = $qb->getQuery()->getResult();

        foreach ($etudiants as $etudiant) {
            $current = $etudiant->getPackageCodes();
            $merged = match ($action) {
                'add' => array_values(array_unique([...$current, ...$inputApps])),
                'remove' => array_values(array_diff($current, $inputApps)),
                default => $inputApps,
            };
            // The intranet is the base access and is never removed.
            $etudiant->setApplications(array_values(array_unique(['intranet', ...$merged])));
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'count' => count($etudiants),
            'applications' => $inputApps,
            'action' => $action,
            'message' => sprintf('Applications mises à jour pour %d étudiant(s).', count($etudiants)),
        ]);
    }
}
