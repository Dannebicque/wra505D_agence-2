<?php

namespace App\Tests\Service\Notification\Source;

use App\Entity\Etudiant\EtudiantNote;
use App\Service\Notification\Source\GradeNotificationSource;
use PHPUnit\Framework\TestCase;

final class GradeNotificationSourceTest extends TestCase
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
        self::assertSame('14,5 / 20', GradeNotificationSource::formatResult($this->note(EtudiantNote::STATUT_PRESENT, 14.5)));
        self::assertSame('0 / 20', GradeNotificationSource::formatResult($this->note(EtudiantNote::STATUT_PRESENT, 0.0)));
    }

    public function testDistingueLesAbsencesEtLaDispense(): void
    {
        self::assertSame('absence injustifiée, compte 0', GradeNotificationSource::formatResult($this->note(EtudiantNote::STATUT_ABSENT_INJUSTIFIE)));
        self::assertSame('absence justifiée, ne compte pas', GradeNotificationSource::formatResult($this->note(EtudiantNote::STATUT_ABSENT_JUSTIFIE)));
        self::assertSame('dispense, ne compte pas', GradeNotificationSource::formatResult($this->note(EtudiantNote::STATUT_DISPENSE)));
    }
}
