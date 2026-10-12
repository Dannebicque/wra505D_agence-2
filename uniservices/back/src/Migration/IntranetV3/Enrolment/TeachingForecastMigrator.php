<?php

namespace App\Migration\IntranetV3\Enrolment;

use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Users\Personnel;
use App\Enum\TypeEnseignementEnum;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Apc\ApcRelationMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Migration\IntranetV3\Structure\AcademicYearMigrator;
use App\Migration\IntranetV3\Users\StaffMigrator;
use App\Utils\LooseValue;
use IntranetBundle\Entity\Previsionnel\Previsionnel;

final class TeachingForecastMigrator extends AbstractMigrator
{
    private const MAX_DIAGNOSTIC_SAMPLES = 20;

    public function getName(): string
    {
        return 'previsionnels-actifs';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [
            ApcRelationMigrator::class,
            StaffMigrator::class,
            AcademicYearMigrator::class,
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
            'enseignement' => 0,
            'enseignementAmbigu' => 0,
            'type' => 0,
        ];

        $sql = <<<'SQL'
SELECT
    p.id,
    p.personnel_id,
    p.annee,
    p.referent,
    p.nb_hcm,
    p.nb_htd,
    p.nb_htp,
    p.nb_gr_cm,
    p.nb_gr_td,
    p.nb_gr_tp,
    p.type_matiere,
    p.id_matiere
FROM previsionnel p
INNER JOIN annee_universitaire au ON au.annee = p.annee
WHERE au.annee = :firstV4Year
ORDER BY p.id
SQL;

        foreach ($this->source->executeQuery($sql, ['firstV4Year' => self::FIRST_V4_ACADEMIC_YEAR])->iterateAssociative() as $row) {
            try {
                $personnel = $this->entityManager->getRepository(Personnel::class)
                    ->findOneBy(['oldId' => LooseValue::castInt($row['personnel_id'])]);
                if (null === $personnel) {
                    ++$skipped;
                    ++$diagnostics['personnel'];
                    $this->sample($messages, $sampleCount, sprintf('Prévisionnel V3 #%s ignoré: personnel #%s non résolu.', LooseValue::nullableString($row['id']), LooseValue::nullableString($row['personnel_id'])));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $anneeUniversitaire = $this->entityManager->getRepository(StructureAnneeUniversitaire::class)
                    ->findOneBy(['annee' => self::FIRST_V4_ACADEMIC_YEAR]);
                if (null === $anneeUniversitaire) {
                    ++$skipped;
                    ++$diagnostics['annee'];
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $type = TypeEnseignementEnum::tryFrom(strtolower(LooseValue::castString($row['type_matiere'])));
                if (null === $type) {
                    ++$skipped;
                    ++$diagnostics['type'];
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $enseignements = $this->findActiveTeachings(LooseValue::castInt($row['id_matiere']), $type, $anneeUniversitaire);
                if ([] === $enseignements) {
                    ++$skipped;
                    ++$diagnostics['enseignement'];
                    $this->sample($messages, $sampleCount, sprintf(
                        'Prévisionnel V3 #%s ignoré: enseignement %s #%s non résolu dans l’année active.',
                        LooseValue::nullableString($row['id']),
                        $type->value,
                        LooseValue::nullableString($row['id_matiere']),
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                if (1 !== count($enseignements)) {
                    ++$skipped;
                    ++$diagnostics['enseignementAmbigu'];
                    $this->sample($messages, $sampleCount, sprintf(
                        'Prévisionnel V3 #%s ignoré: enseignement %s #%s correspond à %d snapshots actifs.',
                        LooseValue::nullableString($row['id']),
                        $type->value,
                        LooseValue::nullableString($row['id_matiere']),
                        count($enseignements),
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $enseignement = $enseignements[0];
                $entity = $this->entityManager->getRepository(Previsionnel::class)->findOneBy([
                    'personnel' => $personnel,
                    'anneeUniversitaire' => $anneeUniversitaire,
                    'enseignement' => $enseignement,
                ]);
                $isNew = null === $entity;
                $entity ??= new Previsionnel();

                $entity
                    ->setPersonnel($personnel)
                    ->setAnneeUniversitaire($anneeUniversitaire)
                    ->setEnseignement($enseignement)
                    ->setReferent((bool) $row['referent'])
                    ->setHeures([
                        'CM' => LooseValue::castFloat($row['nb_hcm'] ?? 0),
                        'TD' => LooseValue::castFloat($row['nb_htd'] ?? 0),
                        'TP' => LooseValue::castFloat($row['nb_htp'] ?? 0),
                        'Projet' => 0.0,
                    ])
                    ->setGroupes([
                        'CM' => LooseValue::castInt($row['nb_gr_cm'] ?? 0),
                        'TD' => LooseValue::castInt($row['nb_gr_td'] ?? 0),
                        'TP' => LooseValue::castInt($row['nb_gr_tp'] ?? 0),
                        'Projet' => 0,
                    ]);

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                $this->sample($messages, $sampleCount, sprintf('Prévisionnel V3 #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage()));
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        if (array_sum($diagnostics) > 0) {
            $messages[] = sprintf(
                'Résumé prévisionnels actifs non migrés: personnels=%d, années=%d, enseignements=%d, enseignements ambigus=%d, types=%d.',
                $diagnostics['personnel'],
                $diagnostics['annee'],
                $diagnostics['enseignement'],
                $diagnostics['enseignementAmbigu'],
                $diagnostics['type'],
            );
        }

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }

    /** @return list<ScolEnseignement> */
    private function findActiveTeachings(
        int $oldId,
        TypeEnseignementEnum $type,
        StructureAnneeUniversitaire $anneeUniversitaire,
    ): array {
        return $this->entityManager->createQueryBuilder()
            ->select('DISTINCT e')
            ->from(ScolEnseignement::class, 'e')
            ->innerJoin('e.enseignementUes', 'eu')
            ->innerJoin('eu.ue', 'ue')
            ->innerJoin('ue.semestre', 's')
            ->innerJoin('s.annee', 'a')
            ->innerJoin('a.pn', 'pn')
            ->andWhere('e.oldId = :oldId')
            ->andWhere('e.type = :type')
            ->andWhere('pn.anneeUniversitaire = :anneeUniversitaire')
            ->setParameter('oldId', $oldId)
            ->setParameter('type', $type)
            ->setParameter('anneeUniversitaire', $anneeUniversitaire)
            ->getQuery()
            ->getResult();
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
