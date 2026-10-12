<?php

declare(strict_types=1);

namespace App\Tests\Document;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Repository\Structure\StructureDepartementPersonnelRepository;
use App\Security\DepartmentPermissionChecker;
use App\Security\DocumentReadAccess;
use DocumentBundle\Entity\Document;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentReadAccessTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function studentVisibilities(): iterable
    {
        yield 'public' => ['PUBLIC', true];
        yield 'étudiant' => ['ETUDIANT', true];
        yield 'département' => ['DEPARTEMENT', true];
        yield 'personnel' => ['PERSONNEL', false];
    }

    #[DataProvider('studentVisibilities')]
    public function testStudentReadsOnlyVisibilitiesOpenToStudents(string $visibility, bool $expected): void
    {
        $document = (new Document())->setVisibility($visibility);

        self::assertSame($expected, $this->access(null)->allows($document, new Etudiant()));
    }

    public function testStaffDoesNotReadStudentOnlyDocument(): void
    {
        $document = (new Document())->setVisibility('ETUDIANT');

        self::assertFalse($this->access(null)->allows($document, new Personnel()));
    }

    public function testStudentReadsDocumentOfOwnDepartment(): void
    {
        $department = new StructureDepartement();
        $document = (new Document())->setVisibility('ETUDIANT')->setDepartement($department);

        self::assertTrue($this->access($department)->allows($document, new Etudiant()));
    }

    public function testStudentDoesNotReadDocumentOfAnotherDepartment(): void
    {
        $document = (new Document())->setVisibility('ETUDIANT')->setDepartement(new StructureDepartement());

        self::assertFalse($this->access(new StructureDepartement())->allows($document, new Etudiant()));
    }

    public function testStudentWithoutDepartmentDoesNotReadDepartmentDocument(): void
    {
        $document = (new Document())->setVisibility('PUBLIC')->setDepartement(new StructureDepartement());

        self::assertFalse($this->access(null)->allows($document, new Etudiant()));
    }

    public function testStaffReadsDocumentOfDefaultDepartment(): void
    {
        $department = new StructureDepartement();
        $document = (new Document())->setVisibility('PERSONNEL')->setDepartement($department);
        $membership = $this->createStub(StructureDepartementPersonnel::class);
        $membership->method('getDepartement')->willReturn($department);
        $staffRepository = $this->createStub(StructureDepartementPersonnelRepository::class);
        $staffRepository->method('findOneBy')->willReturn($membership);

        $access = new DocumentReadAccess($this->createStub(DepartmentPermissionChecker::class), $staffRepository);

        self::assertTrue($access->allows($document, new Personnel()));
    }

    private function access(?StructureDepartement $studentDepartment): DocumentReadAccess
    {
        $checker = $this->createStub(DepartmentPermissionChecker::class);
        $checker->method('getStudentDepartment')->willReturn($studentDepartment);

        return new DocumentReadAccess($checker, $this->createStub(StructureDepartementPersonnelRepository::class));
    }
}
