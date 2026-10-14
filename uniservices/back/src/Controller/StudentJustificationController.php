<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users\Etudiant;
use IntranetBundle\Service\Absence\JustificationFiles;
use IntranetBundle\Service\Absence\StudentJustificationSubmitter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dépôt d'un justificatif d'absence par l'étudiant connecté : période, motif et pièce, tous
 * obligatoires. Les erreurs sont rendues champ par champ, pour s'afficher sous chacun.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class StudentJustificationController extends AbstractController
{
    /** Un justificatif couvre au plus un mois : au-delà, ce n'est plus une absence ponctuelle. */
    private const MAX_DAYS = 31;

    private const MAX_REASON_LENGTH = 2000;

    public function __construct(
        private readonly StudentJustificationSubmitter $submitter,
    ) {
    }

    #[Route('/api/me/justificatifs', name: 'api_me_justification_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $student = $this->getUser();
        if (!$student instanceof Etudiant) {
            throw $this->createAccessDeniedException('Seul un étudiant dépose ses justificatifs.');
        }

        $start = $this->date($request->request->getString('debut'));
        $end = $this->date($request->request->getString('fin'));
        $reason = trim($request->request->getString('motif'));
        $file = $request->files->get('fichier');

        $errors = [];
        if (null === $start) {
            $errors['debut'] = 'Indiquez le début de la période.';
        }
        if (null === $end) {
            $errors['fin'] = 'Indiquez la fin de la période.';
        }
        if (null !== $start && null !== $end && $end < $start) {
            $errors['fin'] = 'La fin doit suivre le début.';
        } elseif (null !== $start && null !== $end && $start->diff($end)->days > self::MAX_DAYS) {
            $errors['fin'] = sprintf('Une période couvre %d jours au plus.', self::MAX_DAYS);
        }
        if ('' === $reason) {
            $errors['motif'] = 'Indiquez le motif de l\'absence.';
        } elseif (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            $errors['motif'] = sprintf('Le motif tient en %d caractères au plus.', self::MAX_REASON_LENGTH);
        }
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $errors['fichier'] = 'Joignez la pièce justificative.';
        } elseif ($file->getSize() > JustificationFiles::MAX_SIZE) {
            $errors['fichier'] = 'La pièce dépasse 5 Mo.';
        } elseif (!in_array($file->getMimeType(), JustificationFiles::MIME_TYPES, true)) {
            $errors['fichier'] = 'La pièce doit être un PDF, un JPG ou un PNG.';
        }

        if ([] !== $errors || null === $end || !$file instanceof UploadedFile) {
            return $this->json(['erreurs' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $semester = $this->submitter->semesterFor($student, $start, $end);
        if (null === $semester) {
            return $this->json(['erreurs' => ['debut' => 'Aucune inscription en cours ne couvre cette période.']], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $justification = $this->submitter->submit($student, $semester, $start, $end, $reason, $file);

        return $this->json([
            'id' => $justification->getId(),
            'etat' => 'en_attente',
            'absencesCouvertes' => $justification->getAbsencesCount(),
        ], JsonResponse::HTTP_CREATED);
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
