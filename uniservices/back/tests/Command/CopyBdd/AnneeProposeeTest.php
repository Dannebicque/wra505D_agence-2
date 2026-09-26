<?php

declare(strict_types=1);

namespace App\Tests\Command\CopyBdd;

use App\Command\CopyBdd\CopyTransfertBddScolariteCommand;
use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use PHPUnit\Framework\TestCase;

/**
 * Reprise de la proposition de poursuite d'une scolarité V3 (fiche E19).
 */
final class AnneeProposeeTest extends TestCase
{
    /** @var array<int, StructureSemestre> */
    private array $semestres = [];
    private StructureAnnee $but2;

    protected function setUp(): void
    {
        $mmi = $this->annees(new StructureDiplome(), ['BUT 1' => [1 => 'S1', 2 => 'S2'], 'BUT 2' => [3 => 'S3']]);
        $this->but2 = $mmi['BUT 2'];
        $this->annees(new StructureDiplome(), ['BUT 2' => [30 => 'S3']]);
    }

    public function testFindsTheYearOfTheSameDiplomaByItsLabel(): void
    {
        self::assertSame($this->but2, CopyTransfertBddScolariteCommand::anneeProposee($this->semestres, 2, 'BUT 2'));
    }

    public function testFindsTheYearByOneOfItsSemesters(): void
    {
        self::assertSame($this->but2, CopyTransfertBddScolariteCommand::anneeProposee($this->semestres, 2, 'S3'));
    }

    public function testLeavesAnUnknownPropositionEmpty(): void
    {
        self::assertNull(CopyTransfertBddScolariteCommand::anneeProposee($this->semestres, 2, 'DUT'));
    }

    public function testLeavesAnUnknownLastSemesterEmpty(): void
    {
        self::assertNull(CopyTransfertBddScolariteCommand::anneeProposee($this->semestres, 99, 'BUT 2'));
    }

    public function testRefusesToChooseBetweenTwoMatchingYears(): void
    {
        $this->annees($this->semestres[1]->getAnnee()?->getDiplome() ?? new StructureDiplome(), ['BUT 2' => [4 => 'S4']]);

        self::assertNull(CopyTransfertBddScolariteCommand::anneeProposee($this->semestres, 2, 'BUT 2'));
    }

    /**
     * @param array<string, array<int, string>> $annees libellé d'année => [ancien id => libellé de semestre]
     *
     * @return array<string, StructureAnnee>
     */
    private function annees(StructureDiplome $diplome, array $annees): array
    {
        $pn = new StructurePn($diplome);
        $creees = [];
        foreach ($annees as $libelle => $semestres) {
            $annee = new StructureAnnee();
            $annee->setLibelle($libelle);
            $annee->setPn($pn);
            foreach ($semestres as $oldId => $libelleSemestre) {
                $semestre = new StructureSemestre();
                $semestre->setLibelle($libelleSemestre);
                $semestre->setOldId($oldId);
                $semestre->setAnnee($annee);
                $this->semestres[$oldId] = $semestre;
            }
            $creees[$libelle] = $annee;
        }

        return $creees;
    }
}
