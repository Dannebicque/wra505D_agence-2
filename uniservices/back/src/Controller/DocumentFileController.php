<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Security\DocumentReadAccess;
use DocumentBundle\Entity\Document;
use DocumentBundle\Services\DocumentFiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contenu d'un document, pour l'aperçu et le téléchargement. Un document que l'utilisateur ne
 * peut pas lire répond 404, comme un document absent : la réponse ne révèle pas qu'il existe.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DocumentFileController extends AbstractController
{
    public function __construct(
        private DocumentReadAccess $access,
        private DocumentFiles $files,
    ) {
    }

    #[Route('/api/me/documents/{id}/fichier', name: 'api_document_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(Document $document): BinaryFileResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Etudiant && !$user instanceof Personnel || !$this->access->allows($document, $user)) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $path = $this->files->path($document);
        if (null === $path) {
            throw $this->createNotFoundException('Ce document n\'a pas de fichier.');
        }

        $mimeType = $document->getMimeType() ?? 'application/octet-stream';
        $filename = basename($path);
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Symfony refuse un nom non ASCII sans repli : un fichier « énoncé.pdf » lèverait une exception.
        $response->setContentDisposition(
            $this->files->isInline($mimeType) ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            (string) preg_replace('/[^\x20-\x7e]|[%\/\\\\]/', '_', $filename),
        );

        return $response;
    }
}
