<?php

namespace App\Migration\IntranetV3\Structure;

use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use App\Enum\TypeGroupeEnum;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;

final class GroupMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'groupes';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [SemesterMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $repository = $this->entityManager->getRepository(StructureGroupe::class);
        $pnRepository = $this->entityManager->getRepository(StructurePn::class);
        $created = $updated = $skipped = $failed = 0;
        $messages = [];
        $unknownTypes = [];
        $legacyParcoursLinks = 0;
        $legacyEduSignIds = 0;

        $schemaManager = $this->source->createSchemaManager();
        $hasJoinTable = $schemaManager->tablesExist(['type_groupe_semestre']);

        foreach ($pnRepository->findAll() as $pn) {
            $diplomeOldId = $pn->getDiplome()?->getOldId();
            if (null === $diplomeOldId) {
                ++$skipped;
                continue;
            }

            if ($hasJoinTable) {
                $groupSql = <<<'SQL'
SELECT DISTINCT g.id, g.parent_id, g.libelle, g.code_apogee, g.ordre, g.parcours_id, g.apc_parcours_id, g.id_edu_sign, tg.type
FROM groupe g
INNER JOIN type_groupe tg ON tg.id = g.type_groupe_id
INNER JOIN type_groupe_semestre tgs ON tgs.type_groupe_id = tg.id
INNER JOIN semestre s ON s.id = tgs.semestre_id
INNER JOIN annee a ON a.id = s.annee_id
WHERE a.diplome_id = :diplome_id
ORDER BY g.id
SQL;
                $linkSql = <<<'SQL'
SELECT DISTINCT g.id AS groupe_id, tgs.semestre_id
FROM groupe g
INNER JOIN type_groupe tg ON tg.id = g.type_groupe_id
INNER JOIN type_groupe_semestre tgs ON tgs.type_groupe_id = tg.id
INNER JOIN semestre s ON s.id = tgs.semestre_id
INNER JOIN annee a ON a.id = s.annee_id
WHERE a.diplome_id = :diplome_id
SQL;
            } else {
                $groupSql = <<<'SQL'
SELECT DISTINCT g.id, g.parent_id, g.libelle, g.code_apogee, g.ordre, g.parcours_id, g.apc_parcours_id, g.id_edu_sign, tg.type
FROM groupe g
INNER JOIN type_groupe tg ON tg.id = g.type_groupe_id
INNER JOIN semestre s ON s.id = tg.semestre_id
INNER JOIN annee a ON a.id = s.annee_id
WHERE a.diplome_id = :diplome_id
ORDER BY g.id
SQL;
                $linkSql = <<<'SQL'
SELECT DISTINCT g.id AS groupe_id, tg.semestre_id
FROM groupe g
INNER JOIN type_groupe tg ON tg.id = g.type_groupe_id
INNER JOIN semestre s ON s.id = tg.semestre_id
INNER JOIN annee a ON a.id = s.annee_id
WHERE a.diplome_id = :diplome_id
SQL;
            }

            $rows = $this->source->fetchAllAssociative($groupSql, ['diplome_id' => $diplomeOldId]);
            $snapshotGroups = [];

            foreach ($rows as $row) {
                try {
                    $type = TypeGroupeEnum::tryFrom(LooseValue::castString($row['type']));
                    if (null === $type) {
                        $unknownTypes[LooseValue::castString($row['type'])] = ($unknownTypes[LooseValue::castString($row['type'])] ?? 0) + 1;
                        $type = TypeGroupeEnum::TYPE_GROUPE_AUTRE;
                    }
                    if (LooseValue::truthyString($row['parcours_id']) !== null || LooseValue::truthyString($row['apc_parcours_id']) !== null) {
                        ++$legacyParcoursLinks;
                    }
                    if (LooseValue::truthyString($row['id_edu_sign']) !== null) {
                        ++$legacyEduSignIds;
                    }

                    $entity = $this->findSnapshotGroup(LooseValue::castInt($row['id']), $pn);
                    $isNew = null === $entity;
                    $entity ??= new StructureGroupe();

                    $entity
                        ->setOldId(LooseValue::castInt($row['id']))
                        ->setLibelle(LooseValue::castString($row['libelle']))
                        ->setType($type)
                        ->setOrdre(null !== $row['ordre'] ? LooseValue::castInt($row['ordre']) : null)
                        ->setCodeApogee(LooseValue::truthyString($row['code_apogee']));

                    if ($isNew) {
                        $this->entityManager->persist($entity);
                        ++$created;
                    } else {
                        ++$updated;
                    }

                    $snapshotGroups[LooseValue::castInt($row['id'])] = $entity;
                } catch (\Throwable $e) {
                    ++$failed;
                    $messages[] = sprintf('Groupe V3 #%s / PN %s: %s', LooseValue::nullableString($row['id']), $pn->getId() ?? 'new', $e->getMessage());
                }
            }

            // On rattache d'abord les groupes aux semestres du même snapshot.
            foreach ($this->source->iterateAssociative($linkSql, ['diplome_id' => $diplomeOldId]) as $link) {
                $groupe = $snapshotGroups[LooseValue::castInt($link['groupe_id'])] ?? $this->findSnapshotGroup(LooseValue::castInt($link['groupe_id']), $pn);
                $semestre = $this->findSnapshotSemestre(LooseValue::castInt($link['semestre_id']), $pn);
                if (null !== $groupe && null !== $semestre) {
                    $groupe->addSemestre($semestre);
                }
            }

            // Puis les relations parent/enfant, toujours à l'intérieur du même PN.
            foreach ($rows as $row) {
                if (null === $row['parent_id']) {
                    continue;
                }

                $entity = $snapshotGroups[LooseValue::castInt($row['id'])] ?? null;
                $parent = $snapshotGroups[LooseValue::castInt($row['parent_id'])] ?? $this->findSnapshotGroup(LooseValue::castInt($row['parent_id']), $pn);
                if (null !== $entity && null !== $parent) {
                    $entity->setParent($parent);
                }
            }

            $this->flush($context);
        }

        if ([] !== $unknownTypes) {
            ksort($unknownTypes);
            $messages[] = 'Types de groupe V3 inconnus convertis en TYPE_GROUPE_AUTRE: '.implode(', ', array_map(
                static fn (string $type, int $count): string => sprintf('%s=%d', $type, $count),
                array_keys($unknownTypes),
                array_values($unknownTypes),
            )).'.';
        }
        if ($legacyParcoursLinks > 0) {
            $messages[] = sprintf('Occurrences groupe V3 avec parcours/apcParcours non transposées automatiquement: %d.', $legacyParcoursLinks);
        }
        if ($legacyEduSignIds > 0) {
            $messages[] = sprintf('Occurrences groupe V3 avec idEduSign sans équivalent dans StructureGroupe V4: %d.', $legacyEduSignIds);
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

    private function findSnapshotGroup(int $oldId, StructurePn $pn): ?StructureGroupe
    {
        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(StructureGroupe::class, 'g')
            ->innerJoin('g.semestres', 's')
            ->innerJoin('s.annee', 'a')
            ->andWhere('g.oldId = :oldId')
            ->andWhere('a.pn = :pn')
            ->setParameter('oldId', $oldId)
            ->setParameter('pn', $pn)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(), StructureGroupe::class);
    }
}
