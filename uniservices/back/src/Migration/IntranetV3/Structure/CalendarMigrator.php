<?php

declare(strict_types=1);

namespace App\Migration\IntranetV3\Structure;

use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Service\Calendrier\CalendrierUniversitaireService;
use App\Utils\LooseValue;

/** Reprend le début annuel depuis V3 ; aucune semaine n’est persistée. */
final class CalendarMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'calendriers';
    }
    public function getDependencies(): array
    {
        return [AcademicYearMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $updated = $skipped = $failed = 0;
        $messages = [];
        /** @var array<string, list<array{semaine_formation: mixed, date_lundi: mixed, semaine_reelle: mixed}>> $groups */
        $groups = [];
        foreach ($this->source->fetchAllAssociative('SELECT semaine_formation, semaine_reelle, date_lundi, annee_universitaire_id FROM calendrier ORDER BY annee_universitaire_id, semaine_formation') as $row) {
            $groups[LooseValue::castString($row['annee_universitaire_id'])][] = [
                'semaine_formation' => $row['semaine_formation'] ?? null,
                'date_lundi' => $row['date_lundi'] ?? null,
                'semaine_reelle' => $row['semaine_reelle'] ?? null,
            ];
        }
        $service = new CalendrierUniversitaireService();
        foreach ($groups as $oldId => $lines) {
            try {
                $year = $this->entityManager->getRepository(StructureAnneeUniversitaire::class)->findOneBy(['oldId' => LooseValue::castInt($oldId)]);
                if ($year === null) {
                    ++$skipped;
                    continue;
                }
                $start = $service->getDateDebutDepuisCorrespondances($lines);
                if ($year->getDateDebutSemaineFormation() !== null && $year->getDateDebutSemaineFormation()->format('Y-m-d') !== $start?->format('Y-m-d')) {
                    throw new \DomainException('La date déjà configurée diffère de celle du calendrier V3.');
                }
                foreach ($lines as $line) {
                    if (LooseValue::castString($line['date_lundi']) > $service->getDateFin($year)->format('Y-m-d')) {
                        throw new \DomainException('Une semaine V3 dépasse le 31 août N+1.');
                    }
                }
                $year->setDateDebutSemaineFormation($start);
                ++$updated;
            } catch (\Throwable $exception) {
                ++$failed;
                $messages[] = sprintf('Calendrier V3 année #%s : %s', $oldId, $exception->getMessage());
            }
        }
        $this->flush($context);
        return new MigrationResult(0, $updated, $skipped, $failed, $messages);
    }
}
