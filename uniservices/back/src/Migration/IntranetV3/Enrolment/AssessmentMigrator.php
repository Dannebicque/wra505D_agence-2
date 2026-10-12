<?php

namespace App\Migration\IntranetV3\Enrolment;

use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Personnel;
use App\Enum\EtatEvaluationEnum;
use App\Enum\TypeEnseignementEnum;
use App\Enum\TypeGroupeEnum;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Apc\ApcResourceMigrator;
use App\Migration\IntranetV3\Apc\ApcSaeMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\Curriculum\SubjectMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Migration\IntranetV3\Structure\AcademicYearMigrator;
use App\Migration\IntranetV3\Users\StaffMigrator;
use App\Utils\LooseValue;
use Symfony\Component\Uid\Uuid;

final class AssessmentMigrator extends AbstractMigrator
{
    private const MAX_DIAGNOSTIC_SAMPLES = 20;

    public function getName(): string
    {
        return 'evaluations';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [
            // Structure teaching migrations are still required for 2026-2027.
            // Historical evaluations (< 2026) only need the academic year and
            // personnel; their teaching context is archived in legacyContext.
            SubjectMigrator::class,
            ApcResourceMigrator::class,
            ApcSaeMigrator::class,
            AcademicYearMigrator::class,
            StaffMigrator::class,
        ];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = $processed = 0;
        $messages = [];
        $diagnostics = [
            'typeMatiere' => 0,
            'anneeUniversitaire' => 0,
            'semestre' => 0,
            'enseignement' => 0,
        ];
        $sampleCount = 0;
        $excludedHistoricalEvaluations = LooseValue::castInt($this->source->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM evaluation e
WHERE e.type_matiere IN ('matiere', 'ressource', 'sae')
  AND NOT EXISTS (
      SELECT 1
      FROM note n
      INNER JOIN etudiant etu ON etu.id = n.etudiant_id
      WHERE n.evaluation_id = e.id
        AND etu.annee_sortie = 0
  )
  AND NOT EXISTS (
      SELECT 1
      FROM evaluation child
      INNER JOIN note n ON n.evaluation_id = child.id
      INNER JOIN etudiant etu ON etu.id = n.etudiant_id
      WHERE child.parent_id = e.id
        AND etu.annee_sortie = 0
  )
SQL));

        $sql = <<<'SQL'
SELECT
    e.id,
    HEX(e.uuid) AS uuid_hex,
    e.personnel_auteur_id,
    e.annee_universitaire_id,
    e.semestre_id,
    e.parent_id,
    e.type_groupe_id,
    e.type_matiere,
    e.id_matiere,
    e.date_evaluation,
    e.visible,
    e.modifiable,
    e.coefficient,
    e.commentaire,
    e.libelle,
    s.libelle AS semestre_libelle,
    s.ordre_annee AS semestre_ordre,
    HEX(parent.uuid) AS parent_uuid_hex
FROM evaluation e
LEFT JOIN evaluation parent ON parent.id = e.parent_id
LEFT JOIN semestre s ON s.id = e.semestre_id
WHERE e.type_matiere IN ('matiere', 'ressource', 'sae')
  AND (
      EXISTS (
          SELECT 1
          FROM note n
          INNER JOIN etudiant etu ON etu.id = n.etudiant_id
          WHERE n.evaluation_id = e.id
            AND etu.annee_sortie = 0
      )
      OR EXISTS (
          SELECT 1
          FROM evaluation child
          INNER JOIN note n ON n.evaluation_id = child.id
          INNER JOIN etudiant etu ON etu.id = n.etudiant_id
          WHERE child.parent_id = e.id
            AND etu.annee_sortie = 0
      )
  )
ORDER BY e.id
SQL;

        foreach ($this->source->executeQuery($sql)->iterateAssociative() as $row) {
            try {
                $enseignementType = $this->mapEnseignementType(LooseValue::castString($row['type_matiere']));
                if (null === $enseignementType) {
                    ++$skipped;
                    ++$diagnostics['typeMatiere'];
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $uuid = $this->resolveUuid($row['uuid_hex'] ?? $row['uuid'] ?? null);
                $anneeUniversitaire = null !== $row['annee_universitaire_id']
                    ? $this->entityManager->getRepository(StructureAnneeUniversitaire::class)
                        ->findOneBy(['oldId' => LooseValue::castInt($row['annee_universitaire_id'])])
                    : null;

                if (null === $anneeUniversitaire) {
                    ++$skipped;
                    ++$diagnostics['anneeUniversitaire'];
                    $this->addSample($messages, $sampleCount, sprintf(
                        'Evaluation V3 #%s ignorée: année universitaire #%s non résolue.',
                        LooseValue::nullableString($row['id']),
                        LooseValue::nullableString($row['annee_universitaire_id']) ?? 'NULL',
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $isNativeV4Year = self::FIRST_V4_ACADEMIC_YEAR === $anneeUniversitaire->getAnnee();
                $semestre = $isNativeV4Year
                    ? $this->findSnapshotSemestre(LooseValue::castInt($row['semestre_id']), $anneeUniversitaire)
                    : null;
                if ($isNativeV4Year && null === $semestre) {
                    ++$skipped;
                    ++$diagnostics['semestre'];
                    $this->addSample($messages, $sampleCount, sprintf(
                        'Evaluation V3 #%s ignorée: semestre V3 #%s non résolu dans le snapshot de l\'année #%s.',
                        LooseValue::nullableString($row['id']),
                        LooseValue::nullableString($row['semestre_id']),
                        LooseValue::nullableString($row['annee_universitaire_id']),
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $enseignement = null !== $semestre
                    ? $this->findSnapshotEnseignement(LooseValue::castInt($row['id_matiere']), $semestre, $enseignementType)
                    : null;
                if ($isNativeV4Year && null === $enseignement) {
                    ++$skipped;
                    ++$diagnostics['enseignement'];
                    $this->addSample($messages, $sampleCount, sprintf(
                        'Evaluation V3 #%s ignorée: enseignement %s V3 #%s non résolu dans le semestre snapshot.',
                        LooseValue::nullableString($row['id']),
                        LooseValue::nullableString($row['type_matiere']),
                        LooseValue::nullableString($row['id_matiere']),
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $evaluation = $this->entityManager->getRepository(ScolEvaluation::class)
                    ->findOneBy(['uuid' => $uuid]);
                $isNew = null === $evaluation;
                $evaluation ??= new ScolEvaluation();

                $evaluation->setUuid($uuid);
                $evaluation
                    ->setLibelle(LooseValue::truthyString($row['libelle']) ?? sprintf('Evaluation #%s', LooseValue::nullableString($row['id'])))
                    ->setCommentaire(LooseValue::truthyString($row['commentaire']))
                    ->setCoeff(null !== $row['coefficient'] ? LooseValue::castFloat($row['coefficient']) : null)
                    ->setDate(null !== $row['date_evaluation'] ? new \DateTime(LooseValue::castString($row['date_evaluation'])) : null)
                    ->setVisible((bool) $row['visible'])
                    ->setModifiable((bool) $row['modifiable'])
                    ->setTypeGroupe($this->mapTypeGroupe(LooseValue::nullableString($row['type_groupe'] ?? null)))
                    ->setAnneeUniversitaire($anneeUniversitaire)
                    ->setSemestre($semestre)
                    ->setEnseignement($enseignement)
                    ->setLegacyContext($isNativeV4Year ? null : [
                        'source' => 'intranet-v3',
                        'semestre' => [
                            'oldId' => LooseValue::castInt($row['semestre_id']),
                            'libelle' => LooseValue::truthyString($row['semestre_libelle']),
                            'ordre' => null !== $row['semestre_ordre'] ? LooseValue::castInt($row['semestre_ordre']) : null,
                        ],
                        'enseignement' => [
                            'oldId' => LooseValue::castInt($row['id_matiere']),
                            'type' => $row['type_matiere'],
                        ],
                    ])
                    ->setEtat((bool) $row['visible'] ? EtatEvaluationEnum::ETAT_PUBLIEE : EtatEvaluationEnum::ETAT_INITIALISEE);

                if (null !== $row['personnel_auteur_id']) {
                    $auteur = $this->entityManager->getRepository(Personnel::class)
                        ->findOneBy(['oldId' => LooseValue::castInt($row['personnel_auteur_id'])]);
                    if (null !== $auteur) {
                        $evaluation->addPersonnelAutorise($auteur);
                    }
                }

                if ($isNew) {
                    $this->entityManager->persist($evaluation);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                $this->addSample($messages, $sampleCount, sprintf(
                    'Evaluation V3 #%s: %s',
                    LooseValue::nullableString($row['id']),
                    $e->getMessage(),
                ));
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        $parentSql = <<<'SQL'
SELECT HEX(e.uuid) AS uuid_hex, HEX(parent.uuid) AS parent_uuid_hex
FROM evaluation e
INNER JOIN evaluation parent ON parent.id = e.parent_id
WHERE e.type_matiere IN ('matiere', 'ressource', 'sae')
  AND (
      EXISTS (
          SELECT 1
          FROM note n
          INNER JOIN etudiant etu ON etu.id = n.etudiant_id
          WHERE n.evaluation_id = e.id
            AND etu.annee_sortie = 0
      )
      OR EXISTS (
          SELECT 1
          FROM evaluation child
          INNER JOIN note n ON n.evaluation_id = child.id
          INNER JOIN etudiant etu ON etu.id = n.etudiant_id
          WHERE child.parent_id = e.id
            AND etu.annee_sortie = 0
      )
  )
SQL;

        foreach ($this->source->executeQuery($parentSql)->iterateAssociative() as $row) {
            try {
                $evaluation = $this->entityManager->getRepository(ScolEvaluation::class)
                    ->findOneBy(['uuid' => $this->uuidFromHex(LooseValue::nullableString($row['uuid_hex']))]);
                $parent = $this->entityManager->getRepository(ScolEvaluation::class)
                    ->findOneBy(['uuid' => $this->uuidFromHex(LooseValue::nullableString($row['parent_uuid_hex']))]);

                if (null !== $evaluation && null !== $parent) {
                    $evaluation->setParent($parent);
                }
            } catch (\Throwable) {
                // Une relation parent invalide ne doit pas bloquer les évaluations déjà migrées.
            }
        }

        $this->flushAndClear($context);

        if (array_sum($diagnostics) > 0) {
            $messages[] = sprintf(
                'Résumé évaluations non migrées: types non supportés=%d, années universitaires=%d, semestres=%d, enseignements=%d.',
                $diagnostics['typeMatiere'],
                $diagnostics['anneeUniversitaire'],
                $diagnostics['semestre'],
                $diagnostics['enseignement'],
            );
        }

        if ($excludedHistoricalEvaluations > 0) {
            $messages[] = sprintf(
                'Évaluations historiques sans note d’étudiant en cours volontairement exclues: %d.',
                $excludedHistoricalEvaluations,
            );
        }

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }

    private function findSnapshotSemestre(int $oldId, StructureAnneeUniversitaire $anneeUniversitaire): ?StructureSemestre
    {
        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('sem')
            ->from(StructureSemestre::class, 'sem')
            ->innerJoin('sem.annee', 'an')
            ->innerJoin('an.pn', 'pn')
            ->andWhere('sem.oldId = :oldId')
            ->andWhere('pn.anneeUniversitaire = :anneeUniversitaire')
            ->setParameter('oldId', $oldId)
            ->setParameter('anneeUniversitaire', $anneeUniversitaire)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(), StructureSemestre::class);
    }

    private function findSnapshotEnseignement(
        int $oldId,
        StructureSemestre $semestre,
        TypeEnseignementEnum $type,
    ): ?ScolEnseignement {
        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('ens')
            ->from(ScolEnseignement::class, 'ens')
            ->innerJoin('ens.enseignementUes', 'ensUe')
            ->innerJoin('ensUe.ue', 'ue')
            ->andWhere('ens.oldId = :oldId')
            ->andWhere('ens.type = :type')
            ->andWhere('ue.semestre = :semestre')
            ->setParameter('oldId', $oldId)
            ->setParameter('type', $type)
            ->setParameter('semestre', $semestre)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(), ScolEnseignement::class);
    }

    private function mapTypeGroupe(?string $type): ?TypeGroupeEnum
    {
        if (null === $type || '' === trim($type)) {
            return null;
        }

        return match (strtoupper(trim($type))) {
            'CM' => TypeGroupeEnum::TYPE_GROUPE_CM,
            'TD' => TypeGroupeEnum::TYPE_GROUPE_TD,
            'TP' => TypeGroupeEnum::TYPE_GROUPE_TP,
            'SPECIAL', 'SPÉCIAL' => TypeGroupeEnum::TYPE_GROUPE_SPECIAL,
            'AUTRE' => TypeGroupeEnum::TYPE_GROUPE_AUTRE,
            default => TypeGroupeEnum::TYPE_GROUPE_AUTRE,
        };
    }

    private function mapEnseignementType(string $type): ?TypeEnseignementEnum
    {
        return match ($type) {
            'matiere' => TypeEnseignementEnum::TYPE_MATIERE,
            'ressource' => TypeEnseignementEnum::TYPE_RESSOURCE,
            'sae' => TypeEnseignementEnum::TYPE_SAE,
            default => null,
        };
    }

    private function uuidFromHex(?string $hex): Uuid
    {
        if (null === $hex || 32 !== strlen($hex)) {
            return Uuid::v4();
        }

        $hex = strtolower($hex);
        return Uuid::fromString(sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ));
    }

    /** @param list<string> $messages */
    private function addSample(array &$messages, int &$sampleCount, string $message): void
    {
        if ($sampleCount >= self::MAX_DIAGNOSTIC_SAMPLES) {
            return;
        }

        $messages[] = $message;
        ++$sampleCount;
    }
}
