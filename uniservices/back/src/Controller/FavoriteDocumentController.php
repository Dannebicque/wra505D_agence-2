<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Entity\Document;
use DocumentBundle\Services\FavoriteDocuments;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Favoris de documents de l'utilisateur connecté. Les adresses ne portent aucun identifiant
 * d'utilisateur : chacun ne lit et ne modifie que les siens.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/api/me/documents-favoris')]
final class FavoriteDocumentController extends AbstractController
{
    public function __construct(
        private FavoriteDocuments $favorites,
    ) {
    }

    #[Route('', name: 'api_favorite_documents', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['documents' => $this->favorites->documentIds($this->user())]);
    }

    #[Route('/{id}', name: 'api_favorite_documents_add', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function add(Document $document): Response
    {
        $this->favorites->add($this->user(), $document);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'api_favorite_documents_remove', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function remove(Document $document): Response
    {
        $this->favorites->remove($this->user(), $document);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function user(): Etudiant|Personnel
    {
        $user = $this->getUser();
        if (!$user instanceof Etudiant && !$user instanceof Personnel) {
            throw $this->createAccessDeniedException('Seuls les étudiants et les personnels ont des favoris.');
        }

        return $user;
    }
}
