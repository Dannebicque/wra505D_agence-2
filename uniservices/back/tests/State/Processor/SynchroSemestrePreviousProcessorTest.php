<?php

declare(strict_types=1);

namespace App\Tests\State\Processor;

use ApiPlatform\Metadata\Post;
use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Etudiant;
use App\Enum\TypeGroupeEnum;
use App\Repository\EtudiantScolariteSemestreRepository;
use App\Repository\Structure\StructureGroupeRepository;
use App\Repository\Structure\StructureSemestreRepository;
use App\State\Processor\EtudiantScolariteSemestre\SynchroSemestrePreviousProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/** Vérifie le report des groupes du semestre précédent par SynchroSemestrePreviousProcessor. */
final class SynchroSemestrePreviousProcessorTest extends TestCase
{
    /** Vérifie qu’un groupe est reporté sur le groupe de même type et de même ordre, et qu’un groupe d’un type non géré reste. */
    public function testReporteLesGroupesParTypeEtOrdre(): void
    {
        $s1 = $this->semestre(1, 1);
        $s2 = $this->semestre(2, 2);

        $td2S1 = $this->groupe(10, TypeGroupeEnum::TYPE_GROUPE_TD, 2);
        $td1S2 = $this->groupe(20, TypeGroupeEnum::TYPE_GROUPE_TD, 1);
        $td2S2 = $this->groupe(21, TypeGroupeEnum::TYPE_GROUPE_TD, 2);
        $special = $this->groupe(30, TypeGroupeEnum::TYPE_GROUPE_SPECIAL, 1);

        $etudiant = new Etudiant();
        (new \ReflectionProperty(Etudiant::class, 'id'))->setValue($etudiant, 5);
        $scolarite = (new EtudiantScolarite())->setEtudiant($etudiant);

        $previous = (new EtudiantScolariteSemestre())->setScolarite($scolarite)->addGroupe($td2S1);
        $current = (new EtudiantScolariteSemestre())->setScolarite($scolarite)->addGroupe($td1S2)->addGroupe($special);

        $semestreRepository = $this->createMock(StructureSemestreRepository::class);
        $semestreRepository->method('find')->willReturn($s2);
        $semestreRepository->method('findOneBy')->willReturn($s1);

        $groupeRepository = $this->createMock(StructureGroupeRepository::class);
        $groupeRepository->method('findAllBySemestreId')->willReturnMap([
            [2, [$td2S2, $td1S2]],
            [1, [$td2S1]],
        ]);

        $scoSemestreRepository = $this->createMock(EtudiantScolariteSemestreRepository::class);
        $scoSemestreRepository->method('findBySemestreAndAnneeUniversitaire')->willReturnMap([
            [2, 24, [$current]],
            [1, 24, [$previous]],
        ]);

        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'semestreId' => 2,
            'anneeUniversitaireId' => 24,
        ], JSON_THROW_ON_ERROR));

        $result = (new SynchroSemestrePreviousProcessor(
            $semestreRepository,
            $groupeRepository,
            $scoSemestreRepository,
            $this->createStub(EntityManagerInterface::class),
        ))->process(null, new Post(), [], ['request' => $request]);

        self::assertSame('ok', $result['status']);
        self::assertSame(1, $result['updatedCount']);
        $ids = array_map(static fn (StructureGroupe $groupe): ?int => $groupe->getId(), $current->getGroupes()->toArray());
        sort($ids);
        self::assertSame([21, 30], $ids);
    }

    private function semestre(int $id, int $ordreAnnee): StructureSemestre
    {
        $semestre = (new StructureSemestre())->setOrdreAnnee($ordreAnnee);
        (new \ReflectionProperty(StructureSemestre::class, 'id'))->setValue($semestre, $id);

        return $semestre;
    }

    private function groupe(int $id, TypeGroupeEnum $type, int $ordre): StructureGroupe
    {
        $groupe = (new StructureGroupe())->setType($type)->setOrdre($ordre);
        (new \ReflectionProperty(StructureGroupe::class, 'id'))->setValue($groupe, $id);

        return $groupe;
    }
}
