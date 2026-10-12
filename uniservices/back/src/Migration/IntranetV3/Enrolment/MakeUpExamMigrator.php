<?php

namespace App\Migration\IntranetV3\Enrolment;

use App\Entity\Salle;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Scolarite\ScolEvaluationRattrapage;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\TypeEnseignementEnum;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Migration\IntranetV3\Structure\RoomMigrator;
use App\Migration\IntranetV3\Users\StudentMigrator;
use App\Migration\IntranetV3\Users\StaffMigrator;
use App\Utils\LooseValue;

final class MakeUpExamMigrator extends AbstractMigrator
{
    private const MAX_DIAGNOSTIC_SAMPLES = 20;

    public function getName(): string
    {
        return 'rattrapages';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [AssessmentMigrator::class, StudentMigrator::class, StaffMigrator::class, RoomMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = $processed = 0;
        $messages = [];
        $sampleCount = 0;
        $diagnostics = [
            'etudiant' => 0,
            'evaluation' => 0,
            'uuid' => 0,
            'salle' => 0,
        ];

        $activeYear = $this->entityManager->getRepository(StructureAnneeUniversitaire::class)
            ->findOneBy(['annee' => self::FIRST_V4_ACADEMIC_YEAR]);

        if (null === $activeYear || null === $activeYear->getOldId()) {
            return new MigrationResult(0, 0, 0, 1, ['Rattrapages: année universitaire active non résolue.']);
        }

        $sql = <<<'SQL'
SELECT
    r.id,
    HEX(r.uuid) AS uuid_hex,
    r.etudiant_id,
    r.personnel_id,
    r.semestre_id,
    r.annee_universitaire_id,
    r.type_matiere,
    r.id_matiere,
    r.date_eval,
    r.heure_eval,
    r.duree,
    r.date_rattrapage,
    r.heure_rattrapage,
    r.salle,
    r.etat_demande
FROM rattrapage r
INNER JOIN annee_universitaire au ON au.id = r.annee_universitaire_id
WHERE au.annee = :firstV4Year
ORDER BY r.id
SQL;

        foreach ($this->source->executeQuery($sql, ['firstV4Year' => self::FIRST_V4_ACADEMIC_YEAR])->iterateAssociative() as $row) {
            try {
                $uuid = $this->resolveUuid($row['uuid_hex'] ?? $row['uuid'] ?? null);

                $etudiant = $this->entityManager->getRepository(Etudiant::class)
                    ->findOneBy(['oldId' => LooseValue::castInt($row['etudiant_id'])]);
                if (null === $etudiant) {
                    ++$skipped;
                    ++$diagnostics['etudiant'];
                    $this->addSample($messages, $sampleCount, sprintf('Rattrapage V3 #%s ignoré: étudiant V3 #%s non résolu.', LooseValue::nullableString($row['id']), LooseValue::nullableString($row['etudiant_id'])));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $type = $this->mapType(LooseValue::nullableString($row['type_matiere'] ?? null));
                $evaluation = null !== $type
                    ? $this->findEvaluation(
                        $activeYear,
                        LooseValue::castInt($row['semestre_id']),
                        LooseValue::castInt($row['id_matiere']),
                        $type,
                        LooseValue::truthyString($row['date_eval']),
                    )
                    : null;

                if (null === $evaluation) {
                    ++$skipped;
                    ++$diagnostics['evaluation'];
                    $this->addSample($messages, $sampleCount, sprintf(
                        'Rattrapage V3 #%s ignoré: évaluation non résolue (semestre #%s, %s #%s, date %s).',
                        LooseValue::nullableString($row['id']),
                        LooseValue::nullableString($row['semestre_id']),
                        LooseValue::nullableString($row['type_matiere']) ?? '?',
                        LooseValue::nullableString($row['id_matiere']),
                        LooseValue::nullableString($row['date_eval']) ?? 'NULL',
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $entity = $this->entityManager->getRepository(ScolEvaluationRattrapage::class)
                    ->findOneBy(['uuid' => $uuid]);
                $isNew = null === $entity;
                $entity ??= new ScolEvaluationRattrapage();
                $entity->setUuid($uuid);

                $personnel = null !== $row['personnel_id']
                    ? $this->entityManager->getRepository(Personnel::class)->findOneBy(['oldId' => LooseValue::castInt($row['personnel_id'])])
                    : null;

                $date = $this->date(LooseValue::truthyString($row['date_rattrapage']));
                $heureDebut = $this->time(LooseValue::truthyString($row['heure_rattrapage']));
                $heureFin = $this->computeEndTime($heureDebut, LooseValue::truthyString($row['duree']));

                $entity
                    ->setEtudiant($etudiant)
                    ->setEvaluation($evaluation)
                    ->setPersonnel($personnel)
                    ->setEtat($this->mapEtat(LooseValue::nullableString($row['etat_demande'] ?? null)))
                    ->setDate($date)
                    ->setHeureDebut($heureDebut)
                    ->setHeureFin($heureFin);

                $salle = $this->findSalle(LooseValue::truthyString($row['salle']));
                if (null !== $row['salle'] && '' !== trim(LooseValue::castString($row['salle'])) && null === $salle) {
                    ++$diagnostics['salle'];
                    $this->addSample($messages, $sampleCount, sprintf(
                        'Rattrapage V3 #%s: salle "%s" non résolue.',
                        LooseValue::nullableString($row['id']),
                        LooseValue::nullableString($row['salle']),
                    ));
                }
                $entity->setSalle($salle);

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                $this->addSample($messages, $sampleCount, sprintf('Rattrapage V3 #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage()));
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        if (array_sum($diagnostics) > 0) {
            $messages[] = sprintf(
                'Résumé rattrapages non migrés (2026-2027): étudiants=%d, évaluations=%d, UUID=%d, salles non résolues=%d.',
                $diagnostics['etudiant'],
                $diagnostics['evaluation'],
                $diagnostics['uuid'],
                $diagnostics['salle'],
            );
        }

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }

    private function findSalle(?string $libelle): ?Salle
    {
        if (null === $libelle || '' === trim($libelle)) {
            return null;
        }

        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Salle::class, 's')
            ->andWhere('LOWER(s.libelle) = :libelle')
            ->setParameter('libelle', strtolower(trim($libelle)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(), Salle::class);
    }

    private function findEvaluation(
        StructureAnneeUniversitaire $anneeUniversitaire,
        int $semestreOldId,
        int $enseignementOldId,
        TypeEnseignementEnum $type,
        ?string $date,
    ): ?ScolEvaluation {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(ScolEvaluation::class, 'e')
            ->innerJoin('e.semestre', 's')
            ->innerJoin('e.enseignement', 'ens')
            ->andWhere('e.anneeUniversitaire = :anneeUniversitaire')
            ->andWhere('s.oldId = :semestreOldId')
            ->andWhere('ens.oldId = :enseignementOldId')
            ->andWhere('ens.type = :type')
            ->setParameter('anneeUniversitaire', $anneeUniversitaire)
            ->setParameter('semestreOldId', $semestreOldId)
            ->setParameter('enseignementOldId', $enseignementOldId)
            ->setParameter('type', $type)
            ->setMaxResults(1);

        if (null !== $date) {
            $qb->andWhere('e.date = :date')
                ->setParameter('date', new \DateTime($date));
        }

        return LooseValue::nullableInstance($qb->getQuery()->getOneOrNullResult(), ScolEvaluation::class);
    }

    private function mapType(?string $type): ?TypeEnseignementEnum
    {
        return match ($type) {
            'matiere' => TypeEnseignementEnum::TYPE_MATIERE,
            'ressource' => TypeEnseignementEnum::TYPE_RESSOURCE,
            'sae' => TypeEnseignementEnum::TYPE_SAE,
            default => null,
        };
    }

    private function mapEtat(?string $etat): ?bool
    {
        return match ($etat) {
            'a' => true,
            'r' => false,
            default => null,
        };
    }

    private function date(?string $value): ?\DateTime
    {
        return null !== $value && '' !== $value ? new \DateTime($value) : null;
    }

    private function time(?string $value): ?\DateTime
    {
        return null !== $value && '' !== $value ? new \DateTime($value) : null;
    }

    private function computeEndTime(?\DateTime $start, ?string $duration): ?\DateTime
    {
        if (null === $start) {
            return null;
        }

        $end = clone $start;
        if (null === $duration || '' === trim($duration)) {
            return $end;
        }

        $duration = trim(strtolower($duration));
        $minutes = 0;

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $duration, $matches) === 1) {
            $minutes = ((int) $matches[1] * 60) + (int) $matches[2];
        } elseif (preg_match('/^(\d+)h(?:\s*(\d+))?$/', $duration, $matches) === 1) {
            $minutes = ((int) $matches[1] * 60) + (isset($matches[2]) ? (int) $matches[2] : 0);
        } elseif (ctype_digit($duration)) {
            $minutes = (int) $duration;
        }

        if ($minutes > 0) {
            $end->modify(sprintf('+%d minutes', $minutes));
        }

        return $end;
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
