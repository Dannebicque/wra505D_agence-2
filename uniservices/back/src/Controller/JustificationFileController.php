<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users\Etudiant;
use App\Security\PostVoter;
use IntranetBundle\Entity\Etudiant\EtudiantAbsenceJustificatif;
use IntranetBundle\Service\Absence\JustificationFiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Pièce d'un justificatif, pour son auteur et pour le personnel qui traite les justificatifs.
 * Pour tout autre, la réponse est 404, comme une pièce absente : elle ne révèle pas qu'elle existe.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class JustificationFileController extends AbstractController
{
    public function __construct(
        private readonly JustificationFiles $files,
    ) {
    }

    #[Route('/api/justificatifs/{id}/fichier', name: 'api_justification_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(EtudiantAbsenceJustificatif $justification): BinaryFileResponse
    {
        $user = $this->getUser();
        $isAuthor = $user instanceof Etudiant && $justification->getScolariteSemestre()?->getScolarite()?->getEtudiant() === $user;
        // Sans sujet, le voter ne laisse passer que le personnel habilité aux justificatifs.
        if (!$isAuthor && !$this->isGranted(PostVoter::CAN_VIEW_JUSTIFICATIF)) {
            throw $this->createNotFoundException('Pièce introuvable.');
        }

        $path = $this->files->path($justification);
        if (null === $path) {
            throw $this->createNotFoundException('Ce justificatif n\'a pas de pièce.');
        }

        $mimeType = MimeTypes::getDefault()->guessMimeType($path) ?? 'application/octet-stream';
        $filename = $justification->getNomFichier() ?? 'justificatif';
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(
            in_array($mimeType, JustificationFiles::MIME_TYPES, true) ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            (string) preg_replace('/[^\x20-\x7e]|[%\/\\\\]/', '_', $filename),
        );

        return $response;
    }
}
