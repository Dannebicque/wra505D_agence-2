<?php

namespace App\Tests\Service\Celcat;

use App\Service\Celcat\CelcatEventConverter;
use PHPUnit\Framework\TestCase;

final class CelcatEventConverterTest extends TestCase
{
    private CelcatEventConverter $convertisseur;

    /** @var array<int, \DateTimeImmutable> */
    private array $lundis;

    protected function setUp(): void
    {
        $this->convertisseur = new CelcatEventConverter();
        $this->lundis = [
            0 => new \DateTimeImmutable('2026-09-07'),
            1 => new \DateTimeImmutable('2026-09-14'),
            2 => new \DateTimeImmutable('2026-09-21'),
            3 => new \DateTimeImmutable('2026-09-28'),
        ];
    }

    /**
     * @param array<string, mixed> $surcharge
     *
     * @return array<string, mixed>
     */
    private function ligne(array $surcharge = []): array
    {
        return array_merge([
            'event_id' => 4210,
            'day_of_week' => 2,
            'start_time' => '1899-12-30 08:00:00',
            'end_time' => '1899-12-30 10:00:00',
            'weeks' => 'YYYY',
            'category' => '[TD]',
            'module_code' => 'R5.01',
            'module_name' => 'Développement front avancé',
            'staff_code' => '1234',
            'staff_name' => 'Annebicque David',
            'room_code' => 'B204',
            'room_name' => 'Salle B204',
            'group_code' => 'MMI3-TD2',
            'group_name' => 'TD2',
            'date_change' => '2026-09-01 17:42:00',
            'room_weeks' => null,
            'notes' => null,
        ], $surcharge);
    }

    public function testDeplieLeMasqueEnUnCreneauParSemaineMarquee(): void
    {
        $creneaux = $this->convertisseur->convertir($this->ligne(['weeks' => 'NYNY']), $this->lundis);

        self::assertSame([1, 3], array_map(fn ($c) => $c->semaine, $creneaux));
    }

    public function testAccepteLeMasqueEnMinuscules(): void
    {
        $creneaux = $this->convertisseur->convertir($this->ligne(['weeks' => 'yNyN']), $this->lundis);

        self::assertSame([0, 2], array_map(fn ($c) => $c->semaine, $creneaux));
    }

    public function testDateLeCreneauAPartirDuLundiEtDuJour(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne(['weeks' => 'NNY', 'day_of_week' => 2]), $this->lundis);

        self::assertSame('2026-09-23', $creneau->date->format('Y-m-d'));
        self::assertSame('Wednesday', $creneau->date->format('l'));
    }

    public function testReporteLesHeuresSurLeJourDuCreneau(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne(['weeks' => 'Y', 'day_of_week' => 0]), $this->lundis);

        self::assertSame('2026-09-07 08:00:00', $creneau->debut->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-07 10:00:00', $creneau->fin->format('Y-m-d H:i:s'));
    }

    public function testLeMasqueDeLaSallePrimeSurCeluiDuCours(): void
    {
        $creneaux = $this->convertisseur->convertir($this->ligne(['weeks' => 'YYYY', 'room_weeks' => 'YNNN']), $this->lundis);

        self::assertSame([0], array_map(fn ($c) => $c->semaine, $creneaux));
    }

    public function testRetombeSurLeMasqueDuCoursSansMasqueDeSalle(): void
    {
        $creneaux = $this->convertisseur->convertir($this->ligne(['weeks' => 'NNYY', 'room_weeks' => null]), $this->lundis);

        self::assertSame([2, 3], array_map(fn ($c) => $c->semaine, $creneaux));
    }

    public function testRetireLesCrochetsDuType(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne(['weeks' => 'Y', 'category' => '[TP]']), $this->lundis);

        self::assertTrue($creneau->estUnCours);
        self::assertSame('TP', $creneau->type);
        self::assertSame('R5.01', $creneau->codeModule);
    }

    public function testTraiteUnEvenementSansMatiereCommeHorsCours(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne([
            'weeks' => 'Y',
            'module_code' => null,
            'category' => 'Réunion',
            'notes' => 'rentrée BUT 3',
        ]), $this->lundis);

        self::assertFalse($creneau->estUnCours);
        self::assertNull($creneau->type);
        self::assertSame('4210', $creneau->codeModule);
        self::assertSame('Réunion rentrée BUT 3', $creneau->libModule);
    }

    public function testIgnoreUneSemaineAbsenteDuCalendrier(): void
    {
        $creneaux = $this->convertisseur->convertir($this->ligne(['weeks' => 'YNNNNNNY']), $this->lundis);

        self::assertSame([0], array_map(fn ($c) => $c->semaine, $creneaux));
    }

    public function testConvertitLeLatin1EnUtf8(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne([
            'weeks' => 'Y',
            'module_name' => mb_convert_encoding('Développement', 'ISO-8859-1', 'UTF-8'),
        ]), $this->lundis);

        self::assertSame('Développement', $creneau->libModule);
    }

    public function testLaisseIntactUnTexteDejaEnUtf8(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne(['weeks' => 'Y', 'staff_name' => 'Hérolt Cyndel']), $this->lundis);

        self::assertSame('Hérolt Cyndel', $creneau->libPersonnel);
    }

    public function testConsidereUnChampVideCommeAbsent(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne(['weeks' => 'Y', 'staff_code' => '', 'room_name' => '   ']), $this->lundis);

        self::assertNull($creneau->codePersonnel);
        self::assertNull($creneau->libSalle);
    }

    public function testIdentifieUnCreneauParLeCoursEtLaSemaine(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne(['weeks' => 'NNNY']), $this->lundis);

        self::assertSame('4210-3', $creneau->cle());
    }

    public function testLitLaDateDeDerniereModification(): void
    {
        [$creneau] = $this->convertisseur->convertir($this->ligne(['weeks' => 'Y']), $this->lundis);

        self::assertSame('2026-09-01 17:42:00', $creneau->modifieLe?->format('Y-m-d H:i:s'));
    }

    public function testNeProduitRienPourUnMasqueVide(): void
    {
        self::assertSame([], $this->convertisseur->convertir($this->ligne(['weeks' => '', 'room_weeks' => null]), $this->lundis));
    }
}
