<?php

declare(strict_types=1);

namespace App\Service\Timetable;

use App\ApiDto\Timetable\StudentTimetable;
use App\Entity\Edt\EdtEvent;
use App\Entity\Users\Etudiant;
use App\Repository\Edt\EdtEventRepository;
use App\Repository\Structure\StructureCalendrierRepository;
use App\Service\Notification\StudentSemesters;

/**
 * Semaine d'emploi du temps de l'étudiant : les cours de ses groupes, la semaine de formation et
 * la plage horaire de son année.
 */
class StudentTimetableBuilder
{
    /**
     * Celcat stocke l'heure murale de Troyes et la colonne ne garde aucun fuseau : on le rend
     * explicite, sans quoi tout client lit l'heure comme de l'UTC.
     */
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(
        private readonly StudentSemesters $studentSemesters,
        private readonly EdtEventRepository $edtEvents,
        private readonly StructureCalendrierRepository $calendar,
    ) {
    }

    public function build(Etudiant $student, \DateTimeImmutable $day): StudentTimetable
    {
        $monday = $day->setTime(0, 0)->modify('monday this week');
        $groupIds = $this->groupIds($student);

        $courses = array_map(
            $this->course(...),
            $this->edtEvents->findByGroupesAndRange($groupIds, $monday, $monday->modify('+7 days')),
        );

        return new StudentTimetable(
            $monday->format('Y-m-d'),
            $this->calendar->findOneBy(['dateLundi' => \DateTime::createFromImmutable($monday)])?->getSemaineFormation(),
            TimeRange::covering($this->edtEvents->findTimesByGroupes($groupIds)),
            $courses,
        );
    }

    /**
     * @return list<int>
     */
    private function groupIds(Etudiant $student): array
    {
        $ids = [];
        foreach ($this->studentSemesters->forStudent($student) as $semester) {
            foreach ($semester->getGroupes() as $group) {
                $id = $group->getId();
                if (null !== $id) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function course(EdtEvent $event): array
    {
        $enseignement = $event->getEnseignement();

        return [
            'id' => $event->getId(),
            'debut' => $this->localTime($event->getDebut()),
            'fin' => $this->localTime($event->getFin()),
            'type' => $event->getType(),
            'codeModule' => $event->getCodeModule(),
            'libModule' => $event->getLibModule(),
            'salle' => $event->getSalle(),
            'enseignant' => $event->getPersonnel()?->getDisplay() ?? $event->getLibPersonnel(),
            'groupe' => $event->getGroupe()?->getLibelle() ?? $event->getLibGroupe(),
            'evaluation' => true === $event->isEvaluation(),
            'enseignement' => null === $enseignement ? null : [
                'id' => $enseignement->getId(),
                'type' => $enseignement->getType()->value,
            ],
        ];
    }

    private function localTime(?\DateTimeInterface $time): ?string
    {
        if (null === $time) {
            return null;
        }

        return (new \DateTimeImmutable($time->format('Y-m-d H:i:s'), new \DateTimeZone(self::TIMEZONE)))
            ->format(\DateTimeInterface::ATOM);
    }
}
