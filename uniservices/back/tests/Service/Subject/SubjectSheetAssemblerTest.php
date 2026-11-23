<?php

declare(strict_types=1);

namespace App\Tests\Service\Subject;

use App\Service\Subject\SubjectSheetAssembler;
use PHPUnit\Framework\TestCase;

final class SubjectSheetAssemblerTest extends TestCase
{
    private SubjectSheetAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new SubjectSheetAssembler();
    }

    /**
     * @return array<string, mixed>
     */
    private function teaching(int $id, string $code, float $coefficient): array
    {
        return [
            'id' => $id,
            'code' => $code,
            'libelle' => 'Matière '.$code,
            'type' => 'Ressource',
            'coefficient' => $coefficient,
            'moyenne' => 13.0,
            'moyennePenalisee' => 12.5,
            'penalite' => 0.5,
            'absencesPenalisantes' => 1,
            'absencesEnAttente' => 0,
            'evaluations' => [['libelle' => 'Projet', 'note' => 13.0]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $ues
     * @param list<array<string, mixed>> $absences
     *
     * @return array<string, mixed>
     */
    private function semester(bool $active, array $ues, array $absences = [], string $year = '2026/2027'): array
    {
        return [
            'libelle' => 'S1',
            'anneeUniversitaire' => $year,
            'actif' => $active,
            'ues' => $ues,
            'absences' => ['liste' => $absences],
        ];
    }

    public function testMergesASubjectSharedByTwoCoursesIntoOneSheet(): void
    {
        $sheet = $this->assembler->assemble([$this->semester(true, [
            ['numero' => 1, 'libelle' => 'UE 1.1', 'enseignements' => [$this->teaching(13, 'R1.06', 2.0), $this->teaching(14, 'R1.10', 2.0)]],
            ['numero' => 2, 'libelle' => 'UE 1.2', 'enseignements' => [$this->teaching(13, 'R1.06', 1.0), $this->teaching(15, 'R1.11', 3.0)]],
        ])], 13);

        self::assertNotNull($sheet);
        self::assertSame('R1.06', $sheet['matiere']['code']);
        self::assertSame([
            ['numero' => 1, 'libelle' => 'UE 1.1', 'coefficient' => 2.0, 'poids' => 50],
            ['numero' => 2, 'libelle' => 'UE 1.2', 'coefficient' => 1.0, 'poids' => 25],
        ], $sheet['matiere']['ues']);
        self::assertSame(12.5, $sheet['notes']['moyennePenalisee']);
        self::assertSame([['libelle' => 'Projet', 'note' => 13.0]], $sheet['notes']['evaluations']);
    }

    public function testKeepsOnlyTheAbsencesOfTheSubject(): void
    {
        $sheet = $this->assembler->assemble([$this->semester(true, [
            ['numero' => 1, 'libelle' => 'UE 1.1', 'enseignements' => [$this->teaching(13, 'R1.06', 1.0)]],
        ], [
            ['debut' => '2026-09-25T10:15:00+00:00', 'code' => 'R1.06', 'justification' => 'refusee'],
            ['debut' => '2026-09-26T10:15:00+00:00', 'code' => 'R1.10', 'justification' => 'aucune'],
            ['debut' => '2026-09-27T10:15:00+00:00', 'code' => null, 'justification' => 'aucune'],
        ])], 13);

        self::assertNotNull($sheet);
        self::assertSame(['2026-09-25T10:15:00+00:00'], array_column($sheet['absences'], 'debut'));
    }

    public function testIgnoresPastYears(): void
    {
        $past = $this->semester(false, [
            ['numero' => 1, 'libelle' => 'UE 1.1', 'enseignements' => [$this->teaching(13, 'R1.06', 1.0)]],
        ], [], '2025/2026');

        self::assertNull($this->assembler->assemble([$past], 13));
    }

    public function testReturnsNullForASubjectTheStudentDoesNotFollow(): void
    {
        $semester = $this->semester(true, [
            ['numero' => 1, 'libelle' => 'UE 1.1', 'enseignements' => [$this->teaching(13, 'R1.06', 1.0)]],
        ]);

        self::assertNull($this->assembler->assemble([$semester], 99));
    }

    public function testFindsTheSubjectInTheActiveSemesterOfTheYear(): void
    {
        $sheet = $this->assembler->assemble([
            $this->semester(false, [['numero' => 1, 'libelle' => 'UE ancienne', 'enseignements' => [$this->teaching(13, 'R1.06', 1.0)]]], [], '2025/2026'),
            $this->semester(true, [['numero' => 1, 'libelle' => 'UE actuelle', 'enseignements' => [$this->teaching(13, 'R1.06', 1.0)]]]),
        ], 13);

        self::assertNotNull($sheet);
        self::assertSame('2026/2027', $sheet['matiere']['anneeUniversitaire']);
        self::assertSame([['numero' => 1, 'libelle' => 'UE actuelle', 'coefficient' => 1.0, 'poids' => 100]], $sheet['matiere']['ues']);
    }
}
