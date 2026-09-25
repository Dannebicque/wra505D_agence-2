<?php

namespace App\Controller;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Entity\Document;
use DocumentBundle\Services\FavorisDocuments;
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
class DocumentFavoriController extends AbstractController
{
    public function __construct(
        private readonly FavorisDocuments $favoris,
    ) {
    }

    #[Route('', name: 'api_documents_favoris', methods: ['GET'])]
    public function liste(): JsonResponse
    {
        return new JsonResponse(['documents' => $this->favoris->idsDocuments($this->utilisateur())]);
    }

    #[Route('/{id}', name: 'api_documents_favoris_ajouter', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function ajouter(Document $document): Response
    {
        $this->favoris->ajouter($this->utilisateur(), $document);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'api_documents_favoris_retirer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function retirer(Document $document): Response
    {
        $this->favoris->retirer($this->utilisateur(), $document);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function utilisateur(): Etudiant|Personnel
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof Etudiant && !$utilisateur instanceof Personnel) {
            throw $this->createAccessDeniedException('Seuls les étudiants et les personnels ont des favoris.');
        }

        return $utilisateur;
    }
}
