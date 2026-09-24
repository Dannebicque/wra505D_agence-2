<?php

namespace App\Tests\Service\Notification\Source;

use App\Entity\Etudiant\EtudiantNote;
use App\Service\Notification\Source\SourceNotes;
use PHPUnit\Framework\TestCase;

final class SourceNotesTest extends TestCase
{
    private function note(string $statut, ?float $valeur = null): EtudiantNote
    {
        $note = (new EtudiantNote())->setPresenceStatut($statut);
        if (null !== $valeur) {
            $note->setNote($valeur);
        }

        return $note;
    }

    public function testEcritLaNoteALaFrancaise(): void
    {
        self::assertSame('14,5 / 20', SourceNotes::resultat($this->note(EtudiantNote::STATUT_PRESENT, 14.5)));
        self::assertSame('0 / 20', SourceNotes::resultat($this->note(EtudiantNote::STATUT_PRESENT, 0.0)));
    }

    public function testDistingueLesAbsencesEtLaDispense(): void
    {
        self::assertSame('absence injustifiée, compte 0', SourceNotes::resultat($this->note(EtudiantNote::STATUT_ABSENT_INJUSTIFIE)));
        self::assertSame('absence justifiée, ne compte pas', SourceNotes::resultat($this->note(EtudiantNote::STATUT_ABSENT_JUSTIFIE)));
        self::assertSame('dispense, ne compte pas', SourceNotes::resultat($this->note(EtudiantNote::STATUT_DISPENSE)));
    }
}
