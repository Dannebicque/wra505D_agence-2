<?php

namespace App\Migration\IntranetV3\Apc;

use App\Entity\Apc\ApcApprentissageCritique;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEnseignementUe;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Structure\StructureUe;
use App\Enum\TypeEnseignementEnum;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;

final class ApcSaeMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'apc-sae';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [ApcCriticalLearningMigrator::class, ApcTeachingUnitLinkMigrator::class, ApcDiplomaLinkMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = 0;
        $messages = [];

        $joinTable = $this->resolveJoinTable(['apc_sae_semestre', 'apc_sae_semestre_semestre']);
        if (null === $joinTable) {
            return new MigrationResult(0, 0, 0, 1, ['Table de liaison SAÉ/semestre V3 introuvable.']);
        }

        foreach ($this->entityManager->getRepository(StructureSemestre::class)->findAll() as $semestre) {
            $semestreOldId = $semestre->getOldId();
            if (null === $semestreOldId) {
                continue;
            }

            $sql = sprintf(<<<'SQL'
SELECT
    s.id, s.libelle, s.libelle_court, s.description,
    s.cm_ppn, s.td_ppn, s.tp_ppn, s.cm_formation, s.td_formation, s.tp_formation,
    s.code_matiere, s.code_element, s.nb_notes, s.suspendu, s.mutualisee,
    s.projet_ppn, s.projet_formation, s.livrables, s.exemples, s.bonification, s.objectifs
FROM apc_sae s
INNER JOIN %s ss ON ss.apc_sae_id = s.id
WHERE ss.semestre_id = :semestre_id
ORDER BY s.id
SQL, $joinTable);

            foreach ($this->source->executeQuery($sql, ['semestre_id' => $semestreOldId])->iterateAssociative() as $row) {
                try {
                    $enseignement = $this->findSnapshotEnseignement(LooseValue::castInt($row['id']), $semestre);
                    $isNew = null === $enseignement;
                    $enseignement ??= new ScolEnseignement();

                    $enseignement
                        ->setOldId(LooseValue::castInt($row['id']))
                        ->setLibelle(LooseValue::castString($row['libelle']))
                        ->setLibelleCourt(LooseValue::truthyString($row['libelle_court']))
                        ->setDescription(LooseValue::truthyString($row['description']))
                        ->setObjectif(LooseValue::truthyString($row['objectifs']))
                        ->setCodeEnseignement(LooseValue::truthyString($row['code_matiere']))
                        ->setCodeApogee(LooseValue::truthyString($row['code_element']))
                        ->setSuspendu((bool) $row['suspendu'])
                        ->setMutualisee((bool) $row['mutualisee'])
                        ->setNbNotes(LooseValue::castInt($row['nb_notes']))
                        ->setType(TypeEnseignementEnum::TYPE_SAE)
                        ->setLivrables(LooseValue::truthyString($row['livrables']))
                        ->setExemple(LooseValue::truthyString($row['exemples']))
                        ->setBonification((bool) $row['bonification'])
                        ->setHeures([
                            'CM' => ['PN' => LooseValue::castFloat($row['cm_ppn']), 'IUT' => LooseValue::castFloat($row['cm_formation'])],
                            'TD' => ['PN' => LooseValue::castFloat($row['td_ppn']), 'IUT' => LooseValue::castFloat($row['td_formation'])],
                            'TP' => ['PN' => LooseValue::castFloat($row['tp_ppn']), 'IUT' => LooseValue::castFloat($row['tp_formation'])],
                            'Projet' => ['PN' => LooseValue::castFloat($row['projet_ppn']), 'IUT' => LooseValue::castFloat($row['projet_formation'])],
                        ]);

                    if ($isNew) {
                        $this->entityManager->persist($enseignement);
                        ++$created;
                    } else {
                        ++$updated;
                    }

                    $this->linkUes($enseignement, LooseValue::castInt($row['id']), $semestre);
                    $this->linkCriticalLearnings($enseignement, LooseValue::castInt($row['id']));
                } catch (\Throwable $e) {
                    ++$failed;
                    if (count($messages) < 20) {
                        $messages[] = sprintf('SAÉ APC V3 #%s / semestre #%s: %s', LooseValue::nullableString($row['id']), LooseValue::nullableString($semestreOldId), $e->getMessage());
                    }
                }
            }

            $this->flush($context);
        }

        $this->flushAndClear($context);

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }

    private function findSnapshotEnseignement(int $oldId, StructureSemestre $semestre): ?ScolEnseignement
    {
        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(ScolEnseignement::class, 'e')
            ->innerJoin('e.enseignementUes', 'eu')
            ->innerJoin('eu.ue', 'ue')
            ->andWhere('e.oldId = :oldId')
            ->andWhere('e.type = :type')
            ->andWhere('ue.semestre = :semestre')
            ->setParameter('oldId', $oldId)
            ->setParameter('type', TypeEnseignementEnum::TYPE_SAE)
            ->setParameter('semestre', $semestre)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(), ScolEnseignement::class);
    }

    private function linkUes(ScolEnseignement $enseignement, int $saeOldId, StructureSemestre $semestre): void
    {
        $rows = $this->source->fetchAllAssociative(
            'SELECT competence_id, coefficient FROM apc_sae_competence WHERE sae_id = :id ORDER BY id',
            ['id' => $saeOldId],
        );

        foreach ($rows as $row) {
            $ue = LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
                ->select('ue')
                ->from(StructureUe::class, 'ue')
                ->innerJoin('ue.competence', 'c')
                ->andWhere('ue.semestre = :semestre')
                ->andWhere('c.oldId = :competenceOldId')
                ->setParameter('semestre', $semestre)
                ->setParameter('competenceOldId', LooseValue::castInt($row['competence_id']))
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult(), StructureUe::class);

            if (null === $ue) {
                continue;
            }

            $link = $this->entityManager->getRepository(ScolEnseignementUe::class)->findOneBy([
                'enseignement' => $enseignement,
                'ue' => $ue,
            ]);
            $link ??= new ScolEnseignementUe($enseignement, $ue);
            $link->setCoefficient(LooseValue::castFloat($row['coefficient']))->setEcts(0.0);
            $this->entityManager->persist($link);
        }
    }

    private function linkCriticalLearnings(ScolEnseignement $enseignement, int $saeOldId): void
    {
        $rows = $this->source->fetchAllAssociative(
            'SELECT apprentissage_critique_id FROM apc_sae_apprentissage_critique WHERE sae_id = :id',
            ['id' => $saeOldId],
        );

        foreach ($rows as $row) {
            $ac = $this->entityManager->getRepository(ApcApprentissageCritique::class)
                ->findOneBy(['oldId' => LooseValue::castInt($row['apprentissage_critique_id'])]);
            if (null !== $ac) {
                $enseignement->addApprentissageCritique($ac);
            }
        }
    }

    /** @param list<string> $candidates */
    private function resolveJoinTable(array $candidates): ?string
    {
        $schema = $this->source->createSchemaManager();
        foreach ($candidates as $candidate) {
            if ($schema->tablesExist([$candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}
