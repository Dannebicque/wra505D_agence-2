<?php

namespace App\Migration\IntranetV3\Enrolment;

use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Etudiant;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;

final class EnrolmentDetailsMigrator extends AbstractMigrator
{
    private const MAX_DIAGNOSTIC_SAMPLES = 20;

    public function getName(): string
    {
        return 'scolarite-details';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [EnrolmentMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;
        $messages = [];
        $sampleCount = 0;
        $propositionsNonMigrees = 0;
        $propositionsMigrees = 0;

        $sql = <<<'SQL'
SELECT
    sc.id,
    sc.etudiant_id,
    sc.semestre_id,
    sc.annee_universitaire_id,
    a.diplome_id,
    sc.decision,
    sc.proposition,
    sc.rang
FROM scolarite sc
INNER JOIN semestre s ON s.id = sc.semestre_id
INNER JOIN annee a ON a.id = s.annee_id
ORDER BY sc.id
SQL;

        $etudiantRepository = $this->entityManager->getRepository(Etudiant::class);
        $anneeUniversitaireRepository = $this->entityManager->getRepository(StructureAnneeUniversitaire::class);
        $diplomeRepository = $this->entityManager->getRepository(StructureDiplome::class);
        $pnRepository = $this->entityManager->getRepository(StructurePn::class);
        $scolariteSemestreRepository = $this->entityManager->getRepository(EtudiantScolariteSemestre::class);

        foreach ($this->source->executeQuery($sql)->iterateAssociative() as $row) {
            try {
                if (null === $row['annee_universitaire_id']) {
                    ++$skipped;
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $etudiant = $etudiantRepository->findOneBy(['oldId' => LooseValue::castInt($row['etudiant_id'])]);
                $anneeUniversitaire = $anneeUniversitaireRepository->findOneBy(['oldId' => LooseValue::castInt($row['annee_universitaire_id'])]);
                $diplome = $diplomeRepository->findOneBy(['oldId' => LooseValue::castInt($row['diplome_id'])]);

                if (null === $etudiant || null === $anneeUniversitaire || null === $diplome) {
                    ++$skipped;
                    $this->addSample($messages, $sampleCount, sprintf(
                        'Scolarite V3 #%s ignorée: étudiant, année universitaire ou diplôme non résolu.',
                        LooseValue::nullableString($row['id']),
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $isNativeV4Year = self::FIRST_V4_ACADEMIC_YEAR === $anneeUniversitaire->getAnnee();
                $pn = $isNativeV4Year ? $pnRepository->findOneBy([
                    'diplome' => $diplome,
                    'anneeUniversitaire' => $anneeUniversitaire,
                ]) : null;
                $semestre = null !== $pn ? $this->findSnapshotSemestre(LooseValue::castInt($row['semestre_id']), $pn) : null;

                if ($isNativeV4Year && (null === $pn || null === $semestre)) {
                    ++$skipped;
                    $this->addSample($messages, $sampleCount, sprintf(
                        'Scolarite V3 #%s ignorée: structure 2026-2027 non résolue.',
                        LooseValue::nullableString($row['id']),
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $scolarite = $this->entityManager->getRepository(\App\Entity\Etudiant\EtudiantScolarite::class)
                    ->findOneBy(['etudiant' => $etudiant, 'anneeUniversitaire' => $anneeUniversitaire]);
                $scolariteSemestre = null;
                if (null !== $scolarite) {
                    foreach ($scolarite->getScolariteSemestre() as $candidate) {
                        if (null !== $semestre && $candidate->getSemestre()?->getId() === $semestre->getId()) {
                            $scolariteSemestre = $candidate;
                            break;
                        }
                        if (null === $semestre && self::legacySemesterOldId($candidate->getLegacyContext()) === LooseValue::castInt($row['semestre_id'])) {
                            $scolariteSemestre = $candidate;
                            break;
                        }
                    }
                }

                if (null === $scolariteSemestre) {
                    ++$skipped;
                    $this->addSample($messages, $sampleCount, sprintf(
                        'Scolarite V3 #%s ignorée: scolarité semestrielle cible non résolue.',
                        LooseValue::nullableString($row['id']),
                    ));
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $scolariteSemestre->setDecision($this->mapDecision($row['decision']));
                $scolariteSemestre->setRang(null !== $row['rang'] ? LooseValue::castInt($row['rang']) : null);

                if (null !== $row['proposition'] && '' !== trim(LooseValue::castString($row['proposition']))) {
                    $proposition = null !== $pn ? ProposedSemesterResolver::resolve(LooseValue::castString($row['proposition']), $pn) : null;
                    if (null !== $proposition) {
                        $scolariteSemestre->setProposition($proposition);
                        ++$propositionsMigrees;
                    } elseif (!in_array(strtoupper(trim(LooseValue::castString($row['proposition']))), ['?', 'E.C.', 'EC'], true)) {
                        ++$propositionsNonMigrees;
                        if (!$isNativeV4Year) {
                            $legacyContext = $scolariteSemestre->getLegacyContext() ?? ['source' => 'intranet-v3'];
                            $legacyContext['proposition'] = LooseValue::castString($row['proposition']);
                            $scolariteSemestre->setLegacyContext($legacyContext);
                        }
                        $this->addSample($messages, $sampleCount, sprintf(
                            'Scolarite V3 #%s: proposition "%s" non résolue vers un semestre du même snapshot.',
                            LooseValue::nullableString($row['id']),
                            LooseValue::castString($row['proposition']),
                        ));
                    }
                }

                ++$updated;
            } catch (\Throwable $e) {
                ++$failed;
                $this->addSample($messages, $sampleCount, sprintf(
                    'Scolarite V3 #%s: %s',
                    LooseValue::nullableString($row['id']),
                    $e->getMessage(),
                ));
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        if ($propositionsMigrees > 0) {
            $messages[] = sprintf('Propositions V3 résolues vers StructureSemestre: %d.', $propositionsMigrees);
        }

        if ($propositionsNonMigrees > 0) {
            $messages[] = sprintf(
                'Propositions V3 non migrées automatiquement: %d. Valeurs laissées à null car aucun semestre du snapshot ne correspond.',
                $propositionsNonMigrees,
            );
        }

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }

    private function findSnapshotSemestre(int $oldId, StructurePn $pn): ?StructureSemestre
    {
        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(StructureSemestre::class, 's')
            ->innerJoin('s.annee', 'a')
            ->andWhere('s.oldId = :oldId')
            ->andWhere('a.pn = :pn')
            ->setParameter('oldId', $oldId)
            ->setParameter('pn', $pn)
            ->getQuery()
            ->getOneOrNullResult(), StructureSemestre::class);
    }


    private function mapDecision(mixed $decision): ?bool
    {
        return match (LooseValue::castString($decision)) {
            'V', 'VCJ', 'VCA' => true,
            'NV', 'DEF' => false,
            default => null,
        };
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
