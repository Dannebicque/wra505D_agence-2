<?php

namespace StageBundle\Migration\IntranetV3;

use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Personnel;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;
use StageBundle\Entity\Stages\StagePeriode;

final class InternshipPeriodMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'stage-periodes';
    }
    public function getDependencies(): array
    {
        return [];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = $processed = 0;
        $messages = [];
        $periodsWithManagers = 0;
        $managersLinked = 0;
        $missingManagers = 0;
        $total = LooseValue::castInt($this->source->fetchOne(
            'SELECT COUNT(*) FROM stage_periode sp INNER JOIN annee_universitaire au ON au.id = sp.annee_universitaire_id WHERE au.active = 1'
        ));
        $this->startProgress($context, 'Périodes de stage', $total);

        $managerIdsByPeriod = [];
        foreach ($this->source->executeQuery(<<<'SQL'
SELECT spp.stage_periode_id, spp.personnel_id
FROM stage_periode_personnel spp
INNER JOIN stage_periode sp ON sp.id = spp.stage_periode_id
INNER JOIN annee_universitaire au ON au.id = sp.annee_universitaire_id
WHERE au.active = 1
ORDER BY spp.stage_periode_id, spp.personnel_id
SQL)->iterateAssociative() as $managerRow) {
            $managerIdsByPeriod[LooseValue::castInt($managerRow['stage_periode_id'])][] = LooseValue::castInt($managerRow['personnel_id']);
        }

        $sql = <<<'SQL'
SELECT sp.id, sp.libelle, sp.nb_semaines, sp.nb_jours, sp.date_debut, sp.date_fin,
       sp.competences_visees, sp.modalite_evaluation, sp.modalite_evaluation_pedagogique,
       sp.modalite_encadrement, sp.document_rendre, sp.semestre_id, sp.dates_flexibles,
       sp.texte_libre, sp.annee_universitaire_id
FROM stage_periode sp
INNER JOIN annee_universitaire au ON au.id = sp.annee_universitaire_id
WHERE au.active = 1
ORDER BY sp.id
SQL;

        foreach ($this->source->executeQuery($sql)->iterateAssociative() as $row) {
            try {
                $annee = $this->entityManager->getRepository(StructureAnneeUniversitaire::class)->findOneBy(['oldId' => LooseValue::castInt($row['annee_universitaire_id'])]);
                if (null === $annee) {
                    ++$skipped;
                    if (count($messages) < 20) {
                        $messages[] = sprintf('StagePeriode #%s: année universitaire V3 #%s introuvable.', LooseValue::nullableString($row['id']), LooseValue::nullableString($row['annee_universitaire_id']));
                    }
                    ++$processed;
                    $context->advanceProgress();
                    continue;
                }

                $semestre = null;
                if (null !== $row['semestre_id']) {
                    $matches = $this->entityManager->getRepository(StructureSemestre::class)->findBy(['oldId' => LooseValue::castInt($row['semestre_id'])]);
                    $matches = array_values(array_filter($matches, static fn (StructureSemestre $candidate): bool => $candidate->getAnnee()?->getPn()?->getAnneeUniversitaire()?->getId() === $annee->getId()));
                    if (1 === count($matches)) {
                        $semestre = $matches[0];
                    }
                }

                $entity = $this->entityManager->getRepository(StagePeriode::class)->findOneBy(['oldId' => LooseValue::castInt($row['id'])]);
                $isNew = null === $entity;
                $entity ??= new StagePeriode();
                $entity->setOldId(LooseValue::castInt($row['id']))->setLibelle(LooseValue::castString($row['libelle']))->setAnneeUniversitaire($annee)->setSemestreProgramme($semestre)
                    ->setNbSemaines(LooseValue::castInt($row['nb_semaines']))->setNbJours(LooseValue::castInt($row['nb_jours']))->setDateDebut(new \DateTime(LooseValue::castString($row['date_debut'])))->setDateFin(new \DateTime(LooseValue::castString($row['date_fin'])))
                    ->setDatesFlexibles((bool) $row['dates_flexibles'])->setCompetencesVisees(LooseValue::nullableString($row['competences_visees']))->setModalitesEvaluationEntreprise(LooseValue::nullableString($row['modalite_evaluation']))
                    ->setModalitesEvaluationPedagogique(LooseValue::nullableString($row['modalite_evaluation_pedagogique']))->setModalitesEncadrement(LooseValue::nullableString($row['modalite_encadrement']))->setDocumentsRendre(LooseValue::nullableString($row['document_rendre']))->setCommentaireLibre(LooseValue::nullableString($row['texte_libre']));

                // V3 had a single unordered ManyToMany collection of managers. In UniServices,
                // the first legacy manager becomes the principal one and the remaining managers
                // become co-responsables. Ordering by personnel_id makes reruns deterministic.
                foreach ($entity->getCoResponsables()->toArray() as $coResponsable) {
                    $entity->removeCoResponsable($coResponsable);
                }
                $entity->setResponsablePrincipal(null);
                $managerIds = $managerIdsByPeriod[LooseValue::castInt($row['id'])] ?? [];
                if ([] !== $managerIds) {
                    ++$periodsWithManagers;
                }
                $resolvedManagers = [];
                foreach ($managerIds as $managerId) {
                    $manager = $this->entityManager->getRepository(Personnel::class)->findOneBy(['oldId' => $managerId]);
                    if (null === $manager) {
                        ++$missingManagers;
                        if (count($messages) < 20) {
                            $messages[] = sprintf('StagePeriode #%s: responsable Personnel V3 #%d introuvable.', LooseValue::nullableString($row['id']), $managerId);
                        }
                        continue;
                    }
                    $resolvedManagers[] = $manager;
                    ++$managersLinked;
                }
                if ([] !== $resolvedManagers) {
                    $entity->setResponsablePrincipal(array_shift($resolvedManagers));
                    foreach ($resolvedManagers as $coResponsable) {
                        $entity->addCoResponsable($coResponsable);
                    }
                }

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                if (count($messages) < 20) {
                    $messages[] = sprintf('StagePeriode #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage());
                }
            }
            ++$processed;
            $context->advanceProgress();
            if (0 === $processed % self::BATCH_SIZE) {
                $this->flushAndClear($context);
            }
        }
        $this->flushAndClear($context);
        $this->finishProgress($context);
        $messages[] = sprintf('%d périodes avec responsable(s), %d rattachement(s) Personnel migré(s).', $periodsWithManagers, $managersLinked);
        if ($missingManagers > 0) {
            $messages[] = sprintf('%d rattachement(s) de responsable ignoré(s): Personnel V3 introuvable.', $missingManagers);
        }
        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }
}
