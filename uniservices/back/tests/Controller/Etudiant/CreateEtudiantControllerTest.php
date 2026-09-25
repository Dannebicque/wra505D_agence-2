<?php

declare(strict_types=1);

namespace App\Tests\Controller\Etudiant;

use App\Controller\Etudiant\CreateEtudiantController;
use App\Entity\Users\Etudiant;
use App\Repository\EtudiantRepository;
use App\Repository\Structure\StructureAnneeRepository;
use App\Repository\Structure\StructureAnneeUniversitaireRepository;
use App\Repository\Structure\StructureSemestreRepository;
use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructureAnneeUniversitaire;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Vérifie l’import d’un étudiant par CreateEtudiantController. */
final class CreateEtudiantControllerTest extends TestCase
{
    /** Vérifie qu’un étudiant importé est persisté sans mot de passe. */
    public function testCreeUnEtudiantSansMotDePasse(): void
    {
        $anneeUniversitaire = $this->createMock(StructureAnneeUniversitaire::class);
        $annee = $this->createMock(StructureAnnee::class);
        $etudiantRepository = $this->createMock(EtudiantRepository::class);
        $etudiantRepository->method('findOneBy')->willReturn(null);
        $structureAnneeRepository = $this->createMock(StructureAnneeRepository::class);
        $structureAnneeRepository->method('findOneBy')->willReturn($annee);
        $structureAnneeUniversitaireRepository = $this->createMock(StructureAnneeUniversitaireRepository::class);
        $structureAnneeUniversitaireRepository->method('findOneBy')->willReturn($anneeUniversitaire);
        $structureSemestreRepository = $this->createMock(StructureSemestreRepository::class);
        $structureSemestreRepository->method('findBy')->willReturn([]);

        $persistedEtudiant = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->with(self::callback(function (object $entity) use (&$persistedEtudiant): bool {
                if ($entity instanceof Etudiant) {
                    $persistedEtudiant = $entity;
                }

                return true;
            }));
        $entityManager->expects(self::once())->method('flush');

        $request = Request::create(
            '/api/etudiants/new',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'anneeUniversitaireId' => 1,
                'fileContent' => "nom;prenom;annee_code_etape\nDupont;Alice;LIC1",
            ], JSON_THROW_ON_ERROR),
        );

        $response = (new CreateEtudiantController(
            $entityManager,
            $etudiantRepository,
            $structureAnneeRepository,
            $structureSemestreRepository,
            $structureAnneeUniversitaireRepository,
        ))->create($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        self::assertInstanceOf(Etudiant::class, $persistedEtudiant);
        self::assertNull($persistedEtudiant->getPassword());
    }
}
