<?php

declare(strict_types=1);

namespace App\Service\Celcat;

use App\Entity\Edt\EdtEvent;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureCalendrier;
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
     * Reprend les semaines de Celcat dans le calendrier d'uniServices, comme
     * MyCelcat::getCalendar() de l'intranet V3. L'écran étudiant en a besoin pour passer
     * d'une semaine du calendrier à une semaine de formation.
     *
     * L'intranet V3 ajoutait des lignes à chaque appel ; on met à jour celle de chaque
     * semaine, pour qu'une relance ne crée pas de doublon.
     *
     * @return int nombre de semaines écrites
     */
    public function synchronizeCalendar(StructureAnneeUniversitaire $academicYear): int
    {
        /** @var array<int, StructureCalendrier> $existing */
        $existing = [];
        foreach ($this->entityManager->getRepository(StructureCalendrier::class)->findBy(['anneeUniversitaire' => $academicYear]) as $week) {
            $existing[(int) $week->getSemaineFormation()] = $week;
        }

        $mondays = $this->source->readWeeks();
        foreach ($mondays as $number => $monday) {
            $week = $existing[$number] ?? null;
            if (null === $week) {
                $week = (new StructureCalendrier())->setAnneeUniversitaire($academicYear)->setSemaineFormation($number);
                $this->entityManager->persist($week);
            }
            // Numéro ISO 8601 : c'est celui que l'écran calcule côté navigateur.
            $week->setDateLundi(\DateTime::createFromImmutable($monday))->setSemaineReelle((int) $monday->format('W'));
        }

        $this->entityManager->flush();

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
     * Même règle que GetSemestreFromGroupe de l'intranet V3 : un groupe sans parcours et
     * rattaché à un seul semestre, sinon le semestre dont le diplôme est celui du parcours.
     */
    private function semesterForGroup(StructureGroupe $group): ?StructureSemestre
    {
        $semesters = $group->getSemestres();
        $track = $group->getParcours();

        if (null === $track && 1 === $semesters->count()) {
            $semester = $semesters->first();

            return false === $semester ? null : $semester;
        }

        foreach ($semesters as $semester) {
            $degree = $semester->getAnnee()?->getDiplome();
            if (null !== $track && null !== $degree && $track->getDiplome()->contains($degree)) {
                return $semester;
            }
        }

        return null;
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
