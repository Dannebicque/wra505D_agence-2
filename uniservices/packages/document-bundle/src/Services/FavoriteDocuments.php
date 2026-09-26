<?php

declare(strict_types=1);

namespace DocumentBundle\Services;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Entity\Document;
use DocumentBundle\Entity\FavoriteDocument;
use DocumentBundle\Repository\FavoriteDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les favoris de chaque utilisateur. Ajouter un favori déjà là, ou retirer un favori absent, ne
 * fait rien : un double clic ou un second onglet ne provoque pas d'erreur.
 */
final readonly class FavoriteDocuments
{
    public function __construct(
        private FavoriteDocumentRepository $favorites,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<int>
     */
    public function documentIds(Etudiant|Personnel $user): array
    {
        return $this->favorites->documentIds($user);
    }

    public function add(Etudiant|Personnel $user, Document $document): void
    {
        if (null !== $this->favorites->findFor($user, $document)) {
            return;
        }

        $this->entityManager->persist(new FavoriteDocument($document, $user));
        $this->entityManager->flush();
    }

    public function remove(Etudiant|Personnel $user, Document $document): void
    {
        $favorite = $this->favorites->findFor($user, $document);
        if (null === $favorite) {
            return;
        }

        $this->entityManager->remove($favorite);
        $this->entityManager->flush();
    }
}
