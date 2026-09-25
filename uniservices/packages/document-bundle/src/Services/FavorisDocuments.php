<?php

namespace DocumentBundle\Services;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Entity\Document;
use DocumentBundle\Entity\DocumentFavori;
use DocumentBundle\Repository\DocumentFavoriRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les favoris de chaque utilisateur. Ajouter un favori déjà là, ou retirer un favori absent, ne
 * fait rien : un double clic ou un second onglet ne provoque pas d'erreur.
 */
class FavorisDocuments
{
    public function __construct(
        private readonly DocumentFavoriRepository $favoris,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<int>
     */
    public function idsDocuments(Etudiant|Personnel $utilisateur): array
    {
        return $this->favoris->idsDocuments($utilisateur);
    }

    public function ajouter(Etudiant|Personnel $utilisateur, Document $document): void
    {
        if (null !== $this->favoris->trouver($utilisateur, $document)) {
            return;
        }

        $this->entityManager->persist(new DocumentFavori($document, $utilisateur));
        $this->entityManager->flush();
    }

    public function retirer(Etudiant|Personnel $utilisateur, Document $document): void
    {
        $favori = $this->favoris->trouver($utilisateur, $document);
        if (null === $favori) {
            return;
        }

        $this->entityManager->remove($favori);
        $this->entityManager->flush();
    }
}
