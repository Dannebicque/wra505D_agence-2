<?php

declare(strict_types=1);

namespace App\Tests\Service\Celcat;

use App\Entity\Edt\EdtEvent;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureCalendrier;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Users\Personnel;
use App\Enum\TypeGroupeEnum;
use App\Service\Celcat\CelcatEventConverter;
use App\Service\Celcat\CelcatSource;
use App\Service\Celcat\CelcatSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use PHPUnit\Framework\TestCase;

final class CelcatSynchronizerTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var array<class-string, list<object>> */
    private array $inDatabase = [];

    /** @var list<object> */
    private array $persisted = [];

    /** @var list<object> */
    private array $deleted = [];

    private StructureAnneeUniversitaire $academicYear;

    protected function setUp(): void
    {
        $this->academicYear = new StructureAnneeUniversitaire();
        $this->inDatabase = [
            EdtEvent::class => [],
            StructureGroupe::class => [$this->group('MMICM', TypeGroupeEnum::TYPE_GROUPE_CM), $this->group('MMITDAB', TypeGroupeEnum::TYPE_GROUPE_TD)],
            Personnel::class => [],
            ScolEnseignement::class => [],
            StructureCalendrier::class => [],
        ];
    }

    private function group(string $code, TypeGroupeEnum $type): StructureGroupe
    {
        return (new StructureGroupe())->setLibelle($code)->setType($type)->setCodeApogee($code);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function addRow(array $override = []): void
    {
        $this->rows[] = array_merge([
            'event_id' => 1,
            'day_of_week' => 0,
            'start_time' => '1899-12-30 08:00:00',
            'end_time' => '1899-12-30 10:00:00',
            'weeks' => 'Y',
            'category' => '[CM]',
            'module_code' => 'R1.02',
            'module_name' => 'Culture numérique',
            'staff_code' => '10001',
            'staff_name' => 'Martin Claire',
            'room_code' => 'AMPHI',
            'room_name' => 'Amphithéâtre',
            'group_code' => 'MMICM',
            'group_name' => 'CM',
            'date_change' => '2026-09-01 12:00:00',
            'room_weeks' => null,
            'notes' => null,
        ], $override);
    }

    private function existingEvent(int $celcatId, string $groupCode): EdtEvent
    {
        $event = (new EdtEvent())
            ->setCelcatId($celcatId)
            ->setSemaineFormation(0)
            ->setJour(0)
            ->setCodeGroupe($groupCode)
            ->setLibModule('ancien libellé');
        $this->inDatabase[EdtEvent::class][] = $event;

        return $event;
    }

    private function synchronizer(): CelcatSynchronizer
    {
        $rows = $this->rows;
        $source = new class ($rows) implements CelcatSource {
            /** @param list<array<string, mixed>> $rows */
            public function __construct(private readonly array $rows)
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function readWeeks(): array
            {
                return [0 => new \DateTimeImmutable('2026-08-31'), 1 => new \DateTimeImmutable('2026-09-07')];
            }

            public function readEvents(int $department): array
            {
                return $this->rows;
            }
        };

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(function (string $className) {
            $repository = $this->createMock(EntityRepository::class);
            $repository->method('findAll')->willReturn($this->inDatabase[$className]);
            $repository->method('findBy')->willReturn($this->inDatabase[$className]);

            return $repository;
        });
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            $this->deleted[] = $entity;
        });

        return new CelcatSynchronizer($source, new CelcatEventConverter(), $entityManager);
    }

    private function synchronize(): \App\Service\Celcat\CelcatReport
    {
        return $this->synchronizer()->synchronize($this->academicYear, 123);
    }

    public function testRestoresCalendarWithIsoWeekNumber(): void
    {
        $weeks = $this->synchronizer()->synchronizeCalendar($this->academicYear);

        /** @var list<StructureCalendrier> $calendar */
        $calendar = $this->persisted;
        self::assertSame(2, $weeks);
        self::assertSame(1, $calendar[1]->getSemaineFormation());
        self::assertSame('2026-09-07', $calendar[1]->getDateLundi()?->format('Y-m-d'));
        self::assertSame(37, $calendar[1]->getSemaineReelle());
    }

    public function testDoesNotDuplicateKnownWeek(): void
    {
        $existing = (new StructureCalendrier())->setAnneeUniversitaire($this->academicYear)->setSemaineFormation(0)->setSemaineReelle(1);
        $this->inDatabase[StructureCalendrier::class] = [$existing];

        $this->synchronizer()->synchronizeCalendar($this->academicYear);

        self::assertCount(1, $this->persisted);
        self::assertSame(36, $existing->getSemaineReelle());
    }

    public function testCreatesSlotPerWeekAndGroup(): void
    {
        $this->addRow(['weeks' => 'YY', 'group_code' => 'MMICM']);
        $this->addRow(['weeks' => 'YY', 'group_code' => 'MMITDAB']);

        $report = $this->synchronize();

        self::assertSame(4, $report->created);
        self::assertCount(4, $this->persisted);
    }

    public function testUpdatesExistingSlotWithoutRecreating(): void
    {
        $existing = $this->existingEvent(1, 'MMICM');
        $this->addRow(['module_name' => 'Nouveau libellé']);

        $report = $this->synchronize();

        self::assertSame(0, $report->created);
        self::assertSame(1, $report->updated);
        self::assertSame([], $this->persisted);
        self::assertSame('Nouveau libellé', $existing->getLibModule());
    }

    public function testDeletesRemovedSlot(): void
    {
        $removed = $this->existingEvent(99, 'MMICM');

        $report = $this->synchronize();

        self::assertSame(1, $report->deleted);
        self::assertSame([$removed], $this->deleted);
    }

    public function testKeepsRemovedSlotWithAbsence(): void
    {
        $this->existingEvent(99, 'MMICM')->addAbsence(new EtudiantAbsence());

        $report = $this->synchronize();

        self::assertSame(0, $report->deleted);
        self::assertSame([], $this->deleted);
        self::assertSame(['99_0_0_MMICM'], $report->kept);
    }

    public function testAttachesGroupAndReportsUnknownCodes(): void
    {
        $this->addRow(['group_code' => 'MMITDAB']);
        $this->addRow(['event_id' => 2, 'group_code' => 'INCONNU']);

        $report = $this->synchronize();

        /** @var EdtEvent $event */
        $event = $this->persisted[0];
        self::assertSame('MMITDAB', $event->getGroupe()?->getCodeApogee());
        self::assertArrayHasKey('INCONNU', $report->unknownGroups);
        self::assertArrayHasKey('10001', $report->unknownStaff);
        self::assertArrayHasKey('R1.02', $report->unknownModules);
    }

    public function testNonCourseUsesGroupTypeAndDefaultsToCm(): void
    {
        $this->addRow(['module_code' => null, 'category' => 'Réunion', 'group_code' => 'MMITDAB']);
        $this->addRow(['event_id' => 2, 'module_code' => null, 'category' => 'Réunion', 'group_code' => 'INCONNU']);

        $this->synchronize();

        /** @var list<EdtEvent> $slots */
        $slots = $this->persisted;
        self::assertSame('TD', $slots[0]->getType());
        self::assertSame('CM', $slots[1]->getType());
    }

    public function testKeepsMostRecentDuplicateSlot(): void
    {
        // Celcat trie par date de modification décroissante : la première ligne est la plus récente.
        $this->addRow(['module_name' => 'Version récente']);
        $this->addRow(['module_name' => 'Version ancienne']);

        $report = $this->synchronize();

        /** @var EdtEvent $event */
        $event = $this->persisted[0];
        self::assertSame(1, $report->created);
        self::assertSame('Version récente', $event->getLibModule());
    }

    public function testTruncatesRoomLabelToColumnLength(): void
    {
        $this->addRow(['room_name' => str_repeat('Salle très longue ', 5)]);

        $this->synchronize();

        /** @var EdtEvent $event */
        $event = $this->persisted[0];
        self::assertSame(25, mb_strlen($event->getSalle()));
    }
}
