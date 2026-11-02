<?php

declare(strict_types=1);

namespace App\State\Processor\EtudiantScolariteSemestre;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructureSemestre;
use App\Repository\EtudiantScolariteSemestreRepository;
use App\Repository\Structure\StructureGroupeRepository;
use App\Repository\Structure\StructureSemestreRepository;
use App\Utils\LooseValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reporte les groupes des étudiants du semestre précédent de la même année sur le semestre courant,
 * en associant les groupes par type et par ordre.
 *
 * @implements ProcessorInterface<mixed, array{updatedCount: int, errorCount: int, status: string, message: string}>
 */
class SynchroSemestrePreviousProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly StructureSemestreRepository $semestreRepository,
        private readonly StructureGroupeRepository $groupeRepository,
        private readonly EtudiantScolariteSemestreRepository $scoSemestreRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return array{updatedCount: int, errorCount: int, status: string, message: string}
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $context['request'] ?? null;
        $payload = $request instanceof Request ? $request->toArray() : [];

        $semestreId = LooseValue::nullableInt($payload['semestreId'] ?? null);
        $anneeUniversitaireId = LooseValue::nullableInt($payload['anneeUniversitaireId'] ?? null);

        if ($semestreId === null || $semestreId === 0 || $anneeUniversitaireId === null || $anneeUniversitaireId === 0) {
            return $this->result('invalid_input', 'Paramètres semestreId et anneeUniversitaireId requis.');
        }

        $currentSemestre = $this->semestreRepository->find($semestreId);
        if (!$currentSemestre instanceof StructureSemestre) {
            return $this->result('semester_not_found', 'Semestre courant introuvable.');
        }

        $previousSemestre = $this->semestreRepository->findOneBy([
            'annee' => $currentSemestre->getAnnee(),
            'ordreAnnee' => ($currentSemestre->getOrdreAnnee() ?? 0) - 1,
        ]);

        if (!$previousSemestre instanceof StructureSemestre) {
            return $this->result('no_previous_semester', 'Impossible de trouver le semestre précédent pour cette année.');
        }

        $currentSemestreId = LooseValue::int($currentSemestre->getId());
        $previousSemestreId = LooseValue::int($previousSemestre->getId());

        $currentStudents = $this->scoSemestreRepository->findBySemestreAndAnneeUniversitaire($currentSemestreId, $anneeUniversitaireId);
        $previousStudents = $this->scoSemestreRepository->findBySemestreAndAnneeUniversitaire($previousSemestreId, $anneeUniversitaireId);

        if ($currentStudents === []) {
            return $this->result('no_students', 'Aucun étudiant à synchroniser pour ce semestre.');
        }

        $currentGroupes = $this->groupeRepository->findAllBySemestreId($currentSemestreId);
        $previousGroupes = $this->groupeRepository->findAllBySemestreId($previousSemestreId);

        $currentGroupByTypeOrder = [];
        $managedTypes = [];
        foreach ($currentGroupes as $groupe) {
            $managedTypes[$groupe->getType()->value] = true;
            if ($groupe->getOrdre() !== null) {
                $currentGroupByTypeOrder[$this->typeOrderKey($groupe)] = $groupe;
            }
        }

        $previousGroupById = [];
        foreach ($previousGroupes as $groupe) {
            $previousGroupById[$groupe->getId()] = $groupe;
        }

        $previousByEtudiantId = [];
        foreach ($previousStudents as $sco) {
            $etudiantId = $sco->getScolarite()?->getEtudiant()?->getId();
            if ($etudiantId !== null) {
                $previousByEtudiantId[$etudiantId] = $sco;
            }
        }

        $updatedCount = 0;
        $errorCount = 0;

        foreach ($currentStudents as $currentSco) {
            $etudiantId = $currentSco->getScolarite()?->getEtudiant()?->getId();
            if ($etudiantId === null || !isset($previousByEtudiantId[$etudiantId])) {
                continue;
            }

            $previousSco = $previousByEtudiantId[$etudiantId];

            $currentExistingGroupes = array_values($currentSco->getGroupes()->toArray());
            $conservedGroupes = array_values(array_filter(
                $currentExistingGroupes,
                static fn (StructureGroupe $groupe): bool => !isset($managedTypes[$groupe->getType()->value])
            ));

            $mappedGroupes = [];
            foreach ($previousSco->getGroupes() as $previousGroupeRef) {
                $previousGroupe = $previousGroupById[$previousGroupeRef->getId()] ?? null;
                if (!$previousGroupe instanceof StructureGroupe || $previousGroupe->getOrdre() === null) {
                    continue;
                }

                $target = $currentGroupByTypeOrder[$this->typeOrderKey($previousGroupe)] ?? null;
                if ($target instanceof StructureGroupe) {
                    $mappedGroupes[$target->getId()] = $target;
                }
            }

            $newGroupes = [...$conservedGroupes, ...array_values($mappedGroupes)];

            $currentIds = array_map(static fn (StructureGroupe $groupe): ?int => $groupe->getId(), $currentExistingGroupes);
            sort($currentIds);

            $newIds = array_map(static fn (StructureGroupe $groupe): ?int => $groupe->getId(), $newGroupes);
            sort($newIds);

            if ($currentIds === $newIds) {
                continue;
            }

            try {
                $currentSco->getGroupes()->clear();
                foreach ($newGroupes as $groupe) {
                    $currentSco->addGroupe($groupe);
                }
                $updatedCount++;
            } catch (\Throwable) {
                $errorCount++;
            }
        }

        $this->entityManager->flush();

        return $this->result('ok', 'Synchronisation terminée.', $updatedCount, $errorCount);
    }

    private function typeOrderKey(StructureGroupe $groupe): string
    {
        return sprintf('%s::%d', $groupe->getType()->value, $groupe->getOrdre() ?? 0);
    }

    /**
     * @return array{updatedCount: int, errorCount: int, status: string, message: string}
     */
    private function result(string $status, string $message, int $updatedCount = 0, int $errorCount = 0): array
    {
        return [
            'updatedCount' => $updatedCount,
            'errorCount' => $errorCount,
            'status' => $status,
            'message' => $message,
        ];
    }
}
