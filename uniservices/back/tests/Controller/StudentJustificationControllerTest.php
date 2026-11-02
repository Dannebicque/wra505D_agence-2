<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\StudentJustificationController;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use IntranetBundle\Entity\Etudiant\EtudiantAbsenceJustificatif;
use IntranetBundle\Service\Absence\StudentJustificationSubmitter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Vérifie le dépôt d'un justificatif d'absence par StudentJustificationController. */
final class StudentJustificationControllerTest extends TestCase
{
    /** Vérifie qu'un membre du personnel ne peut pas déposer un justificatif. */
    public function testRefuseLeDepotPourUnPersonnel(): void
    {
        $submitter = $this->createMock(StudentJustificationSubmitter::class);
        $submitter->expects(self::never())->method('submit');

        $controller = $this->controllerFor($submitter, $this->createMock(Personnel::class));

        $this->expectException(AccessDeniedException::class);

        $controller($this->request('2026-01-05', '2026-01-10', 'Maladie'));
    }

    /** Vérifie qu'un dépôt sans pièce jointe renvoie une erreur sur le fichier. */
    public function testRefuseLeDepotSansFichier(): void
    {
        $submitter = $this->createMock(StudentJustificationSubmitter::class);
        $submitter->expects(self::never())->method('submit');
        $controller = $this->controllerFor($submitter, $this->createMock(Etudiant::class));

        $response = $controller($this->request('2026-01-05', '2026-01-10', 'Maladie'));

        self::assertSame(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertArrayHasKey('fichier', $this->erreurs($response));
    }

    /** Vérifie qu'un fichier texte est refusé avec une erreur sur le fichier. */
    public function testRefuseUnFichierTexte(): void
    {
        $submitter = $this->createMock(StudentJustificationSubmitter::class);
        $submitter->expects(self::never())->method('submit');
        $controller = $this->controllerFor($submitter, $this->createMock(Etudiant::class));

        $response = $controller($this->request(
            '2026-01-05',
            '2026-01-10',
            'Maladie',
            $this->uploadedFile("Ceci n'est pas un PDF.", 'justificatif.txt'),
        ));

        self::assertSame(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertArrayHasKey('fichier', $this->erreurs($response));
    }

    /** Vérifie qu'une fin antérieure au début renvoie une erreur sur la fin. */
    public function testRefuseUneFinAvantLeDebut(): void
    {
        $submitter = $this->createMock(StudentJustificationSubmitter::class);
        $submitter->expects(self::never())->method('submit');
        $controller = $this->controllerFor($submitter, $this->createMock(Etudiant::class));

        $response = $controller($this->request(
            '2026-01-10',
            '2026-01-05',
            'Maladie',
            $this->uploadedFile($this->pdfContent(), 'justificatif.pdf'),
        ));

        self::assertSame(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertArrayHasKey('fin', $this->erreurs($response));
    }

    /** Vérifie qu'une période de plus de 31 jours renvoie une erreur sur la fin. */
    public function testRefuseUnePeriodeDePlusDe31Jours(): void
    {
        $submitter = $this->createMock(StudentJustificationSubmitter::class);
        $submitter->expects(self::never())->method('submit');
        $controller = $this->controllerFor($submitter, $this->createMock(Etudiant::class));

        $response = $controller($this->request(
            '2026-01-01',
            '2026-03-01',
            'Maladie',
            $this->uploadedFile($this->pdfContent(), 'justificatif.pdf'),
        ));

        self::assertSame(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertArrayHasKey('fin', $this->erreurs($response));
    }

    /** Vérifie qu'un motif vide renvoie une erreur sur le motif. */
    public function testRefuseUnMotifVide(): void
    {
        $submitter = $this->createMock(StudentJustificationSubmitter::class);
        $submitter->expects(self::never())->method('submit');
        $controller = $this->controllerFor($submitter, $this->createMock(Etudiant::class));

        $response = $controller($this->request(
            '2026-01-05',
            '2026-01-10',
            '   ',
            $this->uploadedFile($this->pdfContent(), 'justificatif.pdf'),
        ));

        self::assertSame(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertArrayHasKey('motif', $this->erreurs($response));
    }

    /** Vérifie qu'une période sans inscription renvoie une erreur sur le début sans dépôt. */
    public function testRefuseUnePeriodeSansInscription(): void
    {
        $submitter = $this->createMock(StudentJustificationSubmitter::class);
        $submitter->method('semesterFor')->willReturn(null);
        $submitter->expects(self::never())->method('submit');
        $controller = $this->controllerFor($submitter, $this->createMock(Etudiant::class));

        $response = $controller($this->request(
            '2026-01-05',
            '2026-01-10',
            'Maladie',
            $this->uploadedFile($this->pdfContent(), 'justificatif.pdf'),
        ));

        self::assertSame(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertArrayHasKey('debut', $this->erreurs($response));
    }

    /** Vérifie qu'un dépôt valide transmet au service et renvoie le justificatif créé. */
    public function testAccepteUnDepotValide(): void
    {
        $student = $this->createMock(Etudiant::class);
        $semester = $this->createMock(EtudiantScolariteSemestre::class);
        $file = $this->uploadedFile($this->pdfContent(), 'justificatif.pdf');
        $start = new \DateTimeImmutable('2026-01-05');
        $end = new \DateTimeImmutable('2026-01-10');

        $justification = $this->createMock(EtudiantAbsenceJustificatif::class);
        $justification->method('getId')->willReturn(42);
        $justification->method('getAbsencesCount')->willReturn(3);

        $submitter = $this->createMock(StudentJustificationSubmitter::class);
        $submitter->method('semesterFor')->willReturn($semester);
        $submitter->expects(self::once())
            ->method('submit')
            ->with($student, $semester, self::equalTo($start), self::equalTo($end), 'Maladie', $file)
            ->willReturn($justification);

        $controller = $this->controllerFor($submitter, $student);

        $response = $controller($this->request('2026-01-05', '2026-01-10', 'Maladie', $file));

        self::assertSame(JsonResponse::HTTP_CREATED, $response->getStatusCode());
        $body = $this->corps($response);
        self::assertSame(42, $body['id']);
        self::assertSame('en_attente', $body['etat']);
        self::assertSame(3, $body['absencesCouvertes']);
    }

    /** Construit une requête multipart de dépôt de justificatif. */
    private function request(string $debut, string $fin, string $motif, ?UploadedFile $fichier = null): Request
    {
        $request = new Request();
        $request->request->set('debut', $debut);
        $request->request->set('fin', $fin);
        $request->request->set('motif', $motif);
        if (null !== $fichier) {
            $request->files->set('fichier', $fichier);
        }

        return $request;
    }

    /** Prépare le contrôleur avec un conteneur fournissant le jeton de l'utilisateur. */
    private function controllerFor(StudentJustificationSubmitter $submitter, Etudiant|Personnel $user): StudentJustificationController
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => 'security.token_storage' === $id);
        $container->method('get')->willReturnCallback(static fn (string $id): mixed => $tokenStorage);

        $controller = new StudentJustificationController($submitter);
        $controller->setContainer($container);

        return $controller;
    }

    /** Crée un fichier téléversé de test à partir d'un contenu temporaire. */
    private function uploadedFile(string $content, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'justificatif');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    /** Contenu minimal d'un PDF, suffisant pour la détection du type. */
    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    /**
     * Corps JSON de la réponse.
     *
     * @return array<string, mixed>
     */
    private function corps(JsonResponse $response): array
    {
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }

    /**
     * Erreurs de saisie de la réponse, par champ.
     *
     * @return array<string, mixed>
     */
    private function erreurs(JsonResponse $response): array
    {
        $erreurs = $this->corps($response)['erreurs'] ?? null;
        self::assertIsArray($erreurs);

        /** @var array<string, mixed> $erreurs */
        return $erreurs;
    }
}
