<?php

declare(strict_types=1);

namespace App\Service\Celcat;

use App\Entity\Edt\EdtEvent;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Personnel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Aligne les créneaux d'une année et d'un département sur ce que dit Celcat.
 *
 * Les créneaux existants sont mis à jour plutôt que recréés : les absences y sont
 * rattachées, et la base refuse de supprimer un créneau qui en porte.
 */
final class CelcatSynchronizer
{
    public function __construct(
        private readonly CelcatSource $source,
        private readonly CelcatEventConverter $converter,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Fixe le début de la semaine de formation n°1 de l'année d'après les semaines de Celcat :
     * le calendrier d'uniServices se calcule depuis cette seule date (CalendrierUniversitaireService).
     *
     * @return int nombre de semaines lues dans Celcat
     */
    public function synchronizeCalendar(StructureAnneeUniversitaire $academicYear): int
    {
        $mondays = $this->source->readWeeks();
        if ([] !== $mondays) {
            // Celcat numérote ses semaines à partir de 0 ; la semaine de formation n°1 est la sienne n°1.
            $first = min(array_keys($mondays));
            $academicYear->setDateDebutSemaineFormation($mondays[$first]->modify(sprintf('%+d weeks', 1 - $first)));
            $this->entityManager->flush();
        }

        return \count($mondays);
    }

    public function synchronize(StructureAnneeUniversitaire $academicYear, int $department): CelcatReport
    {
        $report = new CelcatReport();
        $mondays = $this->source->readWeeks();

        $groups = $this->indexBy(StructureGroupe::class, fn (StructureGroupe $g) => $g->getCodeApogee());
        $staff = $this->indexBy(Personnel::class, fn (Personnel $p) => null === $p->getNumeroHarpege() ? null : (string) $p->getNumeroHarpege());
        $courses = $this->indexBy(ScolEnseignement::class, fn (ScolEnseignement $e) => $e->getCodeEnseignement());

        /** @var array<string, EdtEvent> $existing */
        $existing = [];
        $existingEvents = $this->entityManager->getRepository(EdtEvent::class)->findBy([
            'anneeUniversitaire' => $academicYear,
            'departementCodeCelcat' => $department,
        ]);
        foreach ($existingEvents as $event) {
            if (null !== $event->getCelcatId()) {
                $existing[$this->key($event)] = $event;
            }
        }

        $seen = [];
        foreach ($this->source->readEvents($department) as $row) {
            foreach ($this->converter->convert($row, $mondays) as $slot) {
                $key = $slot->key();
                // Celcat trie par date de modification décroissante : la première version
                // lue d'un créneau est la plus récente, on ignore les suivantes.
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $event = $existing[$key] ?? null;
                if (null === $event) {
                    $event = new EdtEvent();
                    $this->entityManager->persist($event);
                    ++$report->created;
                } else {
                    ++$report->updated;
                }

                $group = null === $slot->groupCode ? null : ($groups[$slot->groupCode] ?? null);
                $staffMember = null === $slot->staffCode ? null : ($staff[$slot->staffCode] ?? null);
                $course = $slot->isCourse ? ($courses[$slot->moduleCode] ?? null) : null;

                if (null !== $slot->groupCode && null === $group) {
                    $report->unknownGroups[$slot->groupCode] = true;
                }
                if (null !== $slot->staffCode && null === $staffMember) {
                    $report->unknownStaff[$slot->staffCode] = true;
                }
                if ($slot->isCourse && null === $course) {
                    $report->unknownModules[$slot->moduleCode] = true;
                }

                $this->populate($event, $slot, $academicYear, $department, $group, $staffMember, $course);
            }
        }

        foreach ($existing as $key => $event) {
            if (isset($seen[$key])) {
                continue;
            }
            // Un cours retiré de Celcat peut porter des absences déjà relevées : la base
            // refuse la suppression, et effacer ces relevés serait pire. On le signale.
            if ($event->getAbsences()->count() > 0) {
                $report->kept[] = $key;
                continue;
            }
            $this->entityManager->remove($event);
            ++$report->deleted;
        }

        $this->entityManager->flush();

        return $report;
    }

    private function populate(
        EdtEvent $event,
        CelcatSlot $slot,
        StructureAnneeUniversitaire $academicYear,
        int $department,
        ?StructureGroupe $group,
        ?Personnel $staffMember,
        ?ScolEnseignement $course,
    ): void {
        // Un événement qui n'est pas un cours prend le type de son groupe, comme dans
        // l'intranet V3, et CM à défaut.
        $type = $slot->type ?? $group?->getType()->value ?? 'CM';

        $event
            ->setCelcatId($slot->celcatId)
            ->setDepartementCodeCelcat($department)
            ->setAnneeUniversitaire($academicYear)
            ->setSemaineFormation($slot->week)
            ->setJour($slot->day)
            ->setDate(\DateTime::createFromImmutable($slot->date))
            ->setDebut(\DateTime::createFromImmutable($slot->start))
            ->setFin(\DateTime::createFromImmutable($slot->end))
            ->setType($this->truncate($type, 20))
            ->setCodeModule($this->truncate($slot->moduleCode, 20))
            ->setLibModule($this->truncate($slot->moduleLabel, 255))
            ->setEnseignement($course)
            ->setCodePersonnel($this->truncate($slot->staffCode, 20))
            ->setLibPersonnel($this->truncate($slot->staffLabel, 255))
            ->setPersonnel($staffMember)
            ->setSalle($this->truncate($slot->roomLabel ?? $slot->roomCode, 25) ?? '-')
            ->setCodeSalle($this->truncate($slot->roomCode, 25))
            ->setCodeGroupe($this->truncate($slot->groupCode, 30))
            ->setLibGroupe($this->truncate($slot->groupLabel, 255))
            ->setGroupe($group)
            ->setSemestre(null === $group ? null : $this->semesterForGroup($group))
            ->setUpdatedEvent(null === $slot->changedAt ? null : \DateTime::createFromImmutable($slot->changedAt));
    }

    /**
     * Le groupe d'un seul semestre y est rattaché. Le client a retiré le parcours des groupes : un
     * groupe partagé entre plusieurs semestres reste sans semestre, plutôt que d'en deviner un.
     */
    private function semesterForGroup(StructureGroupe $group): ?StructureSemestre
    {
        $semesters = $group->getSemestres();
        if (1 !== $semesters->count()) {
            return null;
        }
        $semester = $semesters->first();

        return false === $semester ? null : $semester;
    }

    private function key(EdtEvent $event): string
    {
        return $event->getCelcatId().'_'.$event->getSemaineFormation().'_'.$event->getJour().'_'.($event->getCodeGroupe() ?? '');
    }

    /**
     * Les colonnes du créneau sont courtes : un libellé Celcat trop long ferait échouer
     * toute la synchronisation pour une seule ligne.
     */
    private function truncate(?string $value, int $length): ?string
    {
        return null === $value ? null : mb_substr($value, 0, $length);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>       $className
     * @param callable(T): ?string  $key
     *
     * @return array<string, T>
     */
    private function indexBy(string $className, callable $key): array
    {
        $index = [];
        foreach ($this->entityManager->getRepository($className)->findAll() as $entity) {
            $value = $key($entity);
            if (null !== $value && '' !== $value) {
                $index[$value] = $entity;
            }
        }

        return $index;
    }
}
