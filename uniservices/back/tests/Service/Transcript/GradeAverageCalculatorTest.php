<?php

declare(strict_types=1);

namespace App\Tests\Service\Transcript;

use App\Entity\Etudiant\EtudiantNote;
use App\Service\Transcript\GradeAverageCalculator;
use PHPUnit\Framework\TestCase;

final class GradeAverageCalculatorTest extends TestCase
{
    private GradeAverageCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new GradeAverageCalculator();
    }

    /**
     * @return array{note: ?float, coefficient: ?float, statut: ?string}
     */
    private function grade(?float $grade, ?float $coefficient = 1.0, string $status = EtudiantNote::STATUT_PRESENT): array
    {
        return ['note' => $grade, 'coefficient' => $coefficient, 'statut' => $status];
    }

    public function testWeightsGradesByEvaluationCoefficient(): void
    {
        self::assertSame(14.0, $this->calculator->teachingAverage([$this->grade(12, 1), $this->grade(15, 2)]));
    }

    public function testCountsUnjustifiedAbsenceAsZero(): void
    {
        $grades = [$this->grade(16), $this->grade(null, 1, EtudiantNote::STATUT_ABSENT_INJUSTIFIE)];

        self::assertSame(8.0, $this->calculator->teachingAverage($grades));
    }

    public function testNeutralizesJustifiedAbsenceAndExemption(): void
    {
        $grades = [
            $this->grade(16),
            $this->grade(null, 1, EtudiantNote::STATUT_ABSENT_JUSTIFIE),
            $this->grade(null, 1, EtudiantNote::STATUT_DISPENSE),
        ];

        self::assertSame(16.0, $this->calculator->teachingAverage($grades));
    }

    public function testDistinguishesRealZeroFromMissingGrade(): void
    {
        self::assertSame(5.0, $this->calculator->teachingAverage([$this->grade(10), $this->grade(0.0)]));
        self::assertSame(10.0, $this->calculator->teachingAverage([$this->grade(10), $this->grade(null)]));
    }

    public function testIgnoresMissingFormValue(): void
    {
        self::assertSame(12.0, $this->calculator->teachingAverage([$this->grade(12), $this->grade(-0.01)]));
    }

    public function testHasNoAverageWithoutCountedGrade(): void
    {
        self::assertNull($this->calculator->teachingAverage([]));
        self::assertNull($this->calculator->teachingAverage([$this->grade(null, 1, EtudiantNote::STATUT_DISPENSE)]));
    }

    public function testUsesOneAsDefaultEvaluationCoefficient(): void
    {
        self::assertSame(11.0, $this->calculator->teachingAverage([$this->grade(10, null), $this->grade(12, null)]));
    }

    public function testWeightsCourseGradesAndIgnoresThoseWithoutAverage(): void
    {
        $teachings = [
            ['moyenne' => 10.0, 'coefficient' => 1.0],
            ['moyenne' => 16.0, 'coefficient' => 2.0],
            ['moyenne' => null, 'coefficient' => 5.0],
        ];

        self::assertSame(14.0, $this->calculator->courseAverage($teachings));
    }

    public function testRoundsToHundredth(): void
    {
        self::assertSame(13.33, $this->calculator->teachingAverage([$this->grade(10), $this->grade(15), $this->grade(15)]));
    }
}
