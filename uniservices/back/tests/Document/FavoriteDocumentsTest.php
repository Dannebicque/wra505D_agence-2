<?php

declare(strict_types=1);

namespace App\Tests\Document;

use App\Entity\Users\Etudiant;
use DocumentBundle\Entity\Document;
use DocumentBundle\Entity\FavoriteDocument;
use DocumentBundle\Repository\FavoriteDocumentRepository;
use DocumentBundle\Services\FavoriteDocuments;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class FavoriteDocumentsTest extends TestCase
{
    public function testAddsAbsentFavorite(): void
    {
        $student = new Etudiant();
        $document = new Document();
        $favorites = $this->createMock(FavoriteDocumentRepository::class);
        $favorites->method('findFor')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            fn (FavoriteDocument $favorite) => $favorite->getDocument() === $document && $favorite->getStudent() === $student && null === $favorite->getStaff(),
        ));
        $entityManager->expects(self::once())->method('flush');

        (new FavoriteDocuments($favorites, $entityManager))->add($student, $document);
    }

    public function testDoesNotAddSameFavoriteTwice(): void
    {
        $student = new Etudiant();
        $document = new Document();
        $favorites = $this->createMock(FavoriteDocumentRepository::class);
        $favorites->method('findFor')->willReturn(new FavoriteDocument($document, $student));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        (new FavoriteDocuments($favorites, $entityManager))->add($student, $document);
    }

    public function testRemovesNothingForAbsentFavorite(): void
    {
        $favorites = $this->createMock(FavoriteDocumentRepository::class);
        $favorites->method('findFor')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');

        (new FavoriteDocuments($favorites, $entityManager))->remove(new Etudiant(), new Document());
    }
}
