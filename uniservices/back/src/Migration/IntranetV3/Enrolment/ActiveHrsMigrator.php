<?php

namespace App\Migration\IntranetV3\Enrolment;

use App\Entity\Personnel\PersonnelEnseignantHrs;
use App\Entity\Personnel\PersonnelEnseignantTypeHrs;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Personnel;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Migration\IntranetV3\Structure\AcademicYearMigrator;
use App\Migration\IntranetV3\Structure\SemesterMigrator;
use App\Migration\IntranetV3\Users\StaffMigrator;
use App\Utils\LooseValue;

final class ActiveHrsMigrator extends AbstractMigrator
{
    private const MAX_DIAGNOSTIC_SAMPLES = 20;

    public function getName(): string
    {
        return 'hrs-actifs';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [
            HrsTypeMigrator::class,
            StaffMigrator::class,
            AcademicYearMigrator::class,
            SemesterMigrator::class,
        ];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = $processed = 0;
        $messages = [];
        $sampleCount = 0;
        $diagnostics = [
            'personnel' => 0,
            'annee' => 0,
            'type' => 0,
            'semestre' => 0,
            'diplome' => 0,
        ];

        $sql = <<<'SQL'
SELECT
    h.id,
    h.nb_heures_td,
    h.libelle,
    h.commentaire,
    h.semestre_id,
    h.diplome_id,
    h.personnel_id,
    h.type_hrs_id,
    h.annee,
    th.libelle AS type_hrs_libelle,
    th.type AS type_hrs_type
FROM hrs h
INNER JOIN annee_universitaire au ON au.annee = h.annee
LEFT JOIN type_hrs th ON th.id = h.type_hrs_id
WHERE au.annee = :firstV4Year
ORDER BY h.id
SQL;

        foreach ($this->source->executeQuery($sql, ['firstV4Year' => self::FIRST_V4_ACADEMIC_YEAR])->iterateAssociative() as $row) {
            try {
                $personnel = $this->entityManager->getRepository(Personnel::class)
                    ->findOneBy(['oldId' => LooseValue::castInt($row['personnel_id'])]);
                $anneeUniversitaire = $this->entityManager->getRepository(StructureAnneeUniversitaire::class)
                    ->findOneBy(['annee' => LooseValue::castInt($row['annee'])]);
                $typeHrs = $this->findTypeHrs($row);

                if (null === $personnel) {
                    ++$skipped;
                    ++$diagnostics['personnel'];
                    $this->sample($messages, $sampleCount, sprintf('HRS V3 #%s ignorée: personnel #%s non résolu.', LooseValue::nullableString($row['id']), LooseValue::nullableString($row['personnel_id'])));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }
                if (null === $anneeUniversitaire) {
                    ++$skipped;
                    ++$diagnostics['annee'];
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }
                if (null === $typeHrs) {
                    ++$skipped;
                    ++$diagnostics['type'];
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $diplome = null;
                if (null !== $row['diplome_id']) {
                    $diplome = $this->entityManager->getRepository(StructureDiplome::class)
                        ->findOneBy(['oldId' => LooseValue::castInt($row['diplome_id'])]);
                    if (null === $diplome) {
                        ++$diagnostics['diplome'];
                    }
                }

                $semestre = null;
                if (null !== $row['semestre_id']) {
                    $semestre = $this->findSnapshotSemestre(LooseValue::castInt($row['semestre_id']), $anneeUniversitaire);
                    if (null === $semestre) {
                        ++$diagnostics['semestre'];
                    }
                }

                $entity = $this->entityManager->getRepository(PersonnelEnseignantHrs::class)->findOneBy([
                    'personnel' => $personnel,
                    'annee_universitaire' => $anneeUniversitaire,
                    'enseignantTypeHrs' => $typeHrs,
                    'libelle' => LooseValue::castString($row['libelle']),
                    'semestre' => $semestre,
                    'diplome' => $diplome,
                ]);
                $isNew = null === $entity;
                $entity ??= new PersonnelEnseignantHrs();

                $entity
                    ->setPersonnel($personnel)
                    ->setAnneeUniversitaire($anneeUniversitaire)
                    ->setEnseignantTypeHrs($typeHrs)
                    ->setLibelle(LooseValue::castString($row['libelle']))
                    ->setCommentaire(LooseValue::truthyString($row['commentaire']))
                    ->setNbHeuresTd(LooseValue::castFloat($row['nb_heures_td'] ?? 0))
                    ->setSemestre($semestre)
                    ->setDiplome($diplome);

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                $this->sample($messages, $sampleCount, sprintf('HRS V3 #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage()));
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        if (0 === $processed) {
            $messages[] = 'Aucune HRS V3 trouvée pour 2026-2027. Répartition V3: '.$this->sourceYearDistribution();
        }

        if (array_sum($diagnostics) > 0) {
            $messages[] = sprintf(
                'Résumé HRS actives: personnels=%d, années=%d, types=%d, semestres non résolus=%d, diplômes non résolus=%d.',
                $diagnostics['personnel'],
                $diagnostics['annee'],
                $diagnostics['type'],
                $diagnostics['semestre'],
                $diagnostics['diplome'],
            );
        }

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }

    /** @param array<string,mixed> $row */
    private function findTypeHrs(array $row): ?PersonnelEnseignantTypeHrs
    {
        if (LooseValue::truthyString($row['type_hrs_libelle']) === null) {
            return null;
        }

        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(PersonnelEnseignantTypeHrs::class, 't')
            ->andWhere('t.libelle = :libelle')
            ->andWhere('(t.type = :type OR (:type IS NULL AND t.type IS NULL))')
            ->setParameter('libelle', LooseValue::castString($row['type_hrs_libelle']))
            ->setParameter('type', LooseValue::truthyString($row['type_hrs_type']))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(), PersonnelEnseignantTypeHrs::class);
    }

    private function findSnapshotSemestre(int $oldId, StructureAnneeUniversitaire $anneeUniversitaire): ?StructureSemestre
    {
        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(StructureSemestre::class, 's')
            ->innerJoin('s.annee', 'a')
            ->innerJoin('a.pn', 'pn')
            ->andWhere('s.oldId = :oldId')
            ->andWhere('pn.anneeUniversitaire = :anneeUniversitaire')
            ->setParameter('oldId', $oldId)
            ->setParameter('anneeUniversitaire', $anneeUniversitaire)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(), StructureSemestre::class);
    }

    private function sourceYearDistribution(): string
    {
        $rows = $this->source->fetchAllAssociative(
            'SELECT h.annee, COUNT(*) AS total FROM hrs h GROUP BY h.annee ORDER BY h.annee'
        );

        if ([] === $rows) {
            return 'aucune HRS dans la table source';
        }

        return implode(', ', array_map(
            static fn (array $row): string => sprintf('%s=%d', LooseValue::nullableString($row['annee']), LooseValue::nullableString($row['total'])),
            $rows,
        ));
    }

    /** @param list<string> $messages */
    private function sample(array &$messages, int &$sampleCount, string $message): void
    {
        if ($sampleCount >= self::MAX_DIAGNOSTIC_SAMPLES) {
            return;
        }

        $messages[] = $message;
        ++$sampleCount;
    }
}
