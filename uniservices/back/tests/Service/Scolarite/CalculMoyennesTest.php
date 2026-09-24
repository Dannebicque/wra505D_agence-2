<?php

namespace App\Tests\Service\Scolarite;

use App\Entity\Etudiant\EtudiantNote;
use App\Service\Scolarite\CalculMoyennes;
use PHPUnit\Framework\TestCase;

final class CalculMoyennesTest extends TestCase
{
    private CalculMoyennes $calcul;

    protected function setUp(): void
    {
        $this->calcul = new CalculMoyennes();
    }

    /**
     * @return array{note: ?float, coefficient: ?float, statut: ?string}
     */
    private function note(?float $note, ?float $coefficient = 1.0, string $statut = EtudiantNote::STATUT_PRESENT): array
    {
        return ['note' => $note, 'coefficient' => $coefficient, 'statut' => $statut];
    }

    public function testPondereLesNotesParLeCoefficientDeLEvaluation(): void
    {
        self::assertSame(14.0, $this->calcul->moyenneEnseignement([$this->note(12, 1), $this->note(15, 2)]));
    }

    public function testCompteUneAbsenceInjustifieePourZero(): void
    {
        $notes = [$this->note(16), $this->note(null, 1, EtudiantNote::STATUT_ABSENT_INJUSTIFIE)];

        self::assertSame(8.0, $this->calcul->moyenneEnseignement($notes));
    }

    public function testNeutraliseUneAbsenceJustifieeEtUneDispense(): void
    {
        $notes = [
            $this->note(16),
            $this->note(null, 1, EtudiantNote::STATUT_ABSENT_JUSTIFIE),
            $this->note(null, 1, EtudiantNote::STATUT_DISPENSE),
        ];

        self::assertSame(16.0, $this->calcul->moyenneEnseignement($notes));
    }

    public function testDistingueUnZeroReelDUneNoteNonSaisie(): void
    {
        self::assertSame(5.0, $this->calcul->moyenneEnseignement([$this->note(10), $this->note(0.0)]));
        self::assertSame(10.0, $this->calcul->moyenneEnseignement([$this->note(10), $this->note(null)]));
    }

    public function testIgnoreLaValeurNonSaisieDuFormulaireDuClient(): void
    {
        self::assertSame(12.0, $this->calcul->moyenneEnseignement([$this->note(12), $this->note(-0.01)]));
    }

    public function testNaPasDeMoyenneSansNoteQuiCompte(): void
    {
        self::assertNull($this->calcul->moyenneEnseignement([]));
        self::assertNull($this->calcul->moyenneEnseignement([$this->note(null, 1, EtudiantNote::STATUT_DISPENSE)]));
    }

    public function testPrendUnCoefficientDeUnQuandLEvaluationNEnAPas(): void
    {
        self::assertSame(11.0, $this->calcul->moyenneEnseignement([$this->note(10, null), $this->note(12, null)]));
    }

    public function testPondereLesMatieresDeLUeEtIgnoreCellesSansMoyenne(): void
    {
        $matieres = [
            ['moyenne' => 10.0, 'coefficient' => 1.0],
            ['moyenne' => 16.0, 'coefficient' => 2.0],
            ['moyenne' => null, 'coefficient' => 5.0],
        ];

        self::assertSame(14.0, $this->calcul->moyenneUe($matieres));
    }

    public function testArrondiAuCentieme(): void
    {
        self::assertSame(13.33, $this->calcul->moyenneEnseignement([$this->note(10), $this->note(15), $this->note(15)]));
    }
}
