<?php

declare(strict_types=1);

namespace App\Tests\Service\Timetable;

use App\Entity\Edt\EdtEvent;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureCalendrier;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Users\Etudiant;
use App\Enum\TypeEnseignementEnum;
use App\Repository\Edt\EdtEventRepository;
use App\Repository\Structure\StructureCalendrierRepository;
use App\Service\Notification\StudentSemesters;
use App\Service\Timetable\StudentTimetableBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StudentTimetableBuilderTest extends TestCase
{
    private StudentSemesters&MockObject $semesters;

    private EdtEventRepository&MockObject $edtEvents;

    private StructureCalendrierRepository&MockObject $calendar;

    protected function setUp(): void
    {
        $this->semesters = $this->createMock(StudentSemesters::class);
        $this->edtEvents = $this->createMock(EdtEventRepository::class);
        $this->calendar = $this->createMock(StructureCalendrierRepository::class);
    }

    private function builder(): StudentTimetableBuilder
    {
        return new StudentTimetableBuilder($this->semesters, $this->edtEvents, $this->calendar);
    }

    /**
     * @param list<int> $ids
     */
    private function semester(array $ids): EtudiantScolariteSemestre
    {
        $groups = array_map(function (int $id) {
            $group = $this->createMock(StructureGroupe::class);
            $group->method('getId')->willReturn($id);

            return $group;
        }, $ids);

        $semester = $this->createMock(EtudiantScolariteSemestre::class);
        $semester->method('getGroupes')->willReturn(new ArrayCollection($groups));

        return $semester;
    }

    public function testLitLaSemaineEntiereDeTousLesGroupesDeLEtudiant(): void
    {
        $this->semesters->method('forStudent')->willReturn([$this->semester([3, 4]), $this->semester([4, 7])]);
        $this->edtEvents->expects(self::once())
            ->method('findByGroupesAndRange')
            ->with([3, 4, 7], new \DateTimeImmutable('2026-10-12'), new \DateTimeImmutable('2026-10-19'))
            ->willReturn([]);
        $this->edtEvents->expects(self::once())->method('findTimesByGroupes')->with([3, 4, 7])->willReturn([]);

        $timetable = $this->builder()->build(new Etudiant(), new \DateTimeImmutable('2026-10-14 15:30'));

        self::assertSame('2026-10-12', $timetable->getWeek());
        self::assertSame(['debut' => '08:00', 'fin' => '20:00'], $timetable->getTimeRange());
    }

    public function testDonneLaSemaineDeFormationDuCalendrier(): void
    {
        $this->semesters->method('forStudent')->willReturn([]);
        $this->edtEvents->method('findByGroupesAndRange')->willReturn([]);
        $this->edtEvents->method('findTimesByGroupes')->willReturn([]);
        $this->calendar->expects(self::once())
            ->method('findOneBy')
            ->with(['dateLundi' => new \DateTime('2026-10-12')])
            ->willReturn((new StructureCalendrier())->setSemaineFormation(6));

        self::assertSame(6, $this->builder()->build(new Etudiant(), new \DateTimeImmutable('2026-10-16'))->getTrainingWeek());
    }

    public function testDonneLesHeuresDeCelcatAvecLeFuseauDeParis(): void
    {
        $this->semesters->method('forStudent')->willReturn([$this->semester([3])]);
        $this->edtEvents->method('findTimesByGroupes')->willReturn([]);
        $this->edtEvents->method('findByGroupesAndRange')->willReturn([
            (new EdtEvent())
                ->setDebut(new \DateTime('2026-10-12 08:00', new \DateTimeZone('UTC')))
                ->setFin(new \DateTime('2026-12-07 10:15', new \DateTimeZone('UTC')))
                ->setType('CM')
                ->setCodeModule('R1.02')
                ->setLibModule('Culture numérique')
                ->setSalle('Amphithéâtre')
                ->setLibPersonnel('Martin Claire')
                ->setLibGroupe('CM')
                ->setEnseignement((new ScolEnseignement())->setType(TypeEnseignementEnum::TYPE_SAE)),
        ]);

        $course = $this->builder()->build(new Etudiant(), new \DateTimeImmutable('2026-10-12'))->getCourses()[0];

        self::assertSame('2026-10-12T08:00:00+02:00', $course['debut'], 'heure d\'été');
        self::assertSame('2026-12-07T10:15:00+01:00', $course['fin'], 'heure d\'hiver');
        self::assertSame('Martin Claire', $course['enseignant']);
        self::assertSame('CM', $course['groupe']);
        self::assertFalse($course['evaluation']);
        self::assertSame(['id' => null, 'type' => 'sae'], $course['enseignement']);
    }

    public function testCalculeLaPlageSurToutesLesSemainesDeLEtudiant(): void
    {
        $this->semesters->method('forStudent')->willReturn([$this->semester([3])]);
        $this->edtEvents->method('findByGroupesAndRange')->willReturn([]);
        $this->edtEvents->method('findTimesByGroupes')->willReturn([
            ['debut' => new \DateTime('2026-09-14 08:00'), 'fin' => new \DateTime('2026-09-14 10:00')],
            ['debut' => new \DateTime('2026-11-16 17:00'), 'fin' => new \DateTime('2026-11-16 19:00')],
        ]);

        self::assertSame(
            ['debut' => '08:00', 'fin' => '19:00'],
            $this->builder()->build(new Etudiant(), new \DateTimeImmutable('2026-09-14'))->getTimeRange(),
        );
    }
}
