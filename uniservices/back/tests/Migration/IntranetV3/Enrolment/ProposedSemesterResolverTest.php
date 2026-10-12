<?php

declare(strict_types=1);

namespace App\Tests\Migration\IntranetV3\Enrolment;

use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use App\Migration\IntranetV3\Enrolment\ProposedSemesterResolver;
use PHPUnit\Framework\TestCase;

/**
 * Règle d'E19, reprise de la commande d'import retirée : la proposition V3 désigne une année ou un
 * semestre du PN, et une ambiguïté ne propose rien.
 */
final class ProposedSemesterResolverTest extends TestCase
{
    private StructurePn $pn;

    /** @var array<string, StructureSemestre> */
    private array $semesters = [];

    protected function setUp(): void
    {
        $this->pn = new StructurePn(new StructureDiplome());
        $this->year('BUT 1', 'Première année', ['S1' => 1, 'S2' => 2]);
        $this->year('BUT 2', 'Deuxième année', ['S4' => 4, 'S3' => 3]);
    }

    public function testFindsTheYearByItsLabelAndProposesItsFirstSemester(): void
    {
        self::assertSame($this->semesters['S3'], ProposedSemesterResolver::resolve('BUT 2', $this->pn));
        self::assertSame($this->semesters['S3'], ProposedSemesterResolver::resolve('Deuxième année', $this->pn));
    }

    public function testFindsASemesterByItsLabel(): void
    {
        self::assertSame($this->semesters['S2'], ProposedSemesterResolver::resolve('S2', $this->pn));
    }

    public function testIgnoresCaseAndSpaces(): void
    {
        self::assertSame($this->semesters['S3'], ProposedSemesterResolver::resolve('  but 2 ', $this->pn));
    }

    public function testLeavesAnUnknownPropositionEmpty(): void
    {
        self::assertNull(ProposedSemesterResolver::resolve('DUT', $this->pn));
    }

    public function testMarksWithoutPropositionGiveNothing(): void
    {
        foreach (['?', 'E.C.', 'ec', ' '] as $mark) {
            self::assertNull(ProposedSemesterResolver::resolve($mark, $this->pn));
        }
    }

    public function testRefusesToChooseBetweenTwoMatchingYears(): void
    {
        $this->year('BUT 2', 'Deuxième année, parcours B', ['S3B' => 3]);

        self::assertNull(ProposedSemesterResolver::resolve('BUT 2', $this->pn));
    }

    /**
     * @param array<string, int> $semesters libellé => ordre LMD
     */
    private function year(string $label, string $longLabel, array $semesters): void
    {
        $year = new StructureAnnee();
        $year->setLibelle($label);
        $year->setLibelleLong($longLabel);
        $this->pn->addAnnee($year);
        foreach ($semesters as $semesterLabel => $order) {
            $semester = new StructureSemestre();
            $semester->setLibelle($semesterLabel);
            $semester->setOrdreLmd($order);
            $year->addSemestre($semester);
            $this->semesters[$semesterLabel] = $semester;
        }
    }
}
