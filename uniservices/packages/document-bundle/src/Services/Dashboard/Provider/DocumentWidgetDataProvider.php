<?php

declare(strict_types=1);

namespace DocumentBundle\Services\Dashboard\Provider;

use App\Domain\Dashboard\WidgetDataProviderInterface;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Repository\DocumentCategoryRepository;
use DocumentBundle\Repository\FavoriteDocumentRepository;
use DocumentBundle\Repository\DocumentRepository;

class DocumentWidgetDataProvider implements WidgetDataProviderInterface
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentCategoryRepository $categoryRepository,
        private readonly FavoriteDocumentRepository $favoriteRepository,
    ) {
    }

    public function supports(string $code): bool
    {
        return str_starts_with($code, 'document.');
    }

    /** @return array<string, mixed> */
    public function getData(string $code, Personnel|Etudiant $user): array
    {
        return match ($code) {
            'document.recents' => [
                'items' => array_map(
                    fn ($doc) => [
                        'id' => $doc->getId(),
                        'title' => $doc->getTitre(),
                        'type' => $doc->getType(),
                        'size' => $doc->getFileSize(),
                        'category' => $doc->getCategory()?->getLibelle() ?? 'Général',
                        'updatedAt' => $doc->getUpdatedAt()->format('d/m/Y H:i'),
                        'author' => $doc->getAuthor(),
                    ],
                    $this->documentRepository->findBy([], ['createdAt' => 'DESC'], 5)
                ),
            ],
            'document.stats' => [
                'totalDocuments' => $this->documentRepository->count([]),
                'totalCategories' => $this->categoryRepository->count([]),
                'favoriteCount' => $this->favoriteRepository->countFor($user),
            ],
            default => [],
        };
    }
}
