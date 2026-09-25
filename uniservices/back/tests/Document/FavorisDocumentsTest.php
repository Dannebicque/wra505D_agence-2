<?php

namespace App\Tests\Document;

use App\Entity\Users\Etudiant;
use DocumentBundle\Entity\Document;
use DocumentBundle\Entity\DocumentFavori;
use DocumentBundle\Repository\DocumentFavoriRepository;
use DocumentBundle\Services\FavorisDocuments;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class FavorisDocumentsTest extends TestCase
{
    public function testAjouteUnFavoriAbsent(): void
    {
        $etudiant = new Etudiant();
        $document = new Document();
        $favoris = $this->createMock(DocumentFavoriRepository::class);
        $favoris->method('trouver')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            fn (DocumentFavori $favori) => $favori->getDocument() === $document && $favori->getEtudiant() === $etudiant && null === $favori->getPersonnel(),
        ));
        $entityManager->expects(self::once())->method('flush');

        (new FavorisDocuments($favoris, $entityManager))->ajouter($etudiant, $document);
    }

    public function testNAjoutePasDeuxFoisLeMemeFavori(): void
    {
        $etudiant = new Etudiant();
        $document = new Document();
        $favoris = $this->createMock(DocumentFavoriRepository::class);
        $favoris->method('trouver')->willReturn(new DocumentFavori($document, $etudiant));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        (new FavorisDocuments($favoris, $entityManager))->ajouter($etudiant, $document);
    }

    public function testRetirerUnFavoriAbsentNeFaitRien(): void
    {
        $favoris = $this->createMock(DocumentFavoriRepository::class);
        $favoris->method('trouver')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');

        (new FavorisDocuments($favoris, $entityManager))->retirer(new Etudiant(), new Document());
    }
}
