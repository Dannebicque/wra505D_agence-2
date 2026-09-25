<?php

namespace App\Tests\Dashboard;

use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Users\Etudiant;
use App\Repository\Edt\EdtEventRepository;
use App\Repository\Structure\StructureDepartementRepository;
use App\Service\Notification\StudentSemesters;
use AuthBundle\Services\Dashboard\Provider\AuthWidgetDataProvider;
use Doctrine\Common\Collections\ArrayCollection;
use IntranetBundle\Services\Dashboard\Provider\IntranetWidgetDataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le widget « Aujourd'hui » de l'étudiant lit les cours de ses groupes : il annonçait « Aucun
 * événement aujourd'hui » quel que soit l'emploi du temps.
 */
final class EmploiDuTempsWidgetTest extends TestCase
{
    /**
     * @param list<int> $ids
     */
    private function semestre(array $ids): EtudiantScolariteSemestre
    {
        $groupes = array_map(function (int $id) {
            $groupe = $this->createMock(StructureGroupe::class);
            $groupe->method('getId')->willReturn($id);

            return $groupe;
        }, $ids);

        $semestre = $this->createMock(EtudiantScolariteSemestre::class);
        $semestre->method('getGroupes')->willReturn(new ArrayCollection($groupes));

        return $semestre;
    }

    public function testLitLesCoursDuJourDeTousLesGroupesDeLEtudiant(): void
    {
        $semesters = $this->createMock(StudentSemesters::class);
        $semesters->method('forStudent')->willReturn([$this->semestre([3, 4]), $this->semestre([4, 7])]);

        $today = new \DateTimeImmutable('today');
        $edtEvents = $this->createMock(EdtEventRepository::class);
        $edtEvents->expects(self::never())->method('findByPersonnelAndRange');
        $edtEvents->expects(self::once())
            ->method('findByGroupesAndRange')
            ->with([3, 4, 7], $today, $today->modify('+1 day'))
            ->willReturn([]);

        $provider = new IntranetWidgetDataProvider(
            $edtEvents,
            $this->createMock(StructureDepartementRepository::class),
            $this->createMock(AuthWidgetDataProvider::class),
            $semesters,
        );

        self::assertSame([], $provider->getData('intranet.emploi_du_temps', new Etudiant())['items']);
    }
}
