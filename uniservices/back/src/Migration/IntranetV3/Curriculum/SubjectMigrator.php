<?php

namespace App\Migration\IntranetV3\Curriculum;

use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEnseignementUe;
use App\Entity\Structure\StructureUe;
use App\Enum\TypeEnseignementEnum;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;

final class SubjectMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'matieres';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [TeachingUnitMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = 0;
        $messages = [];

        foreach ($this->entityManager->getRepository(StructureUe::class)->findAll() as $ue) {
            $ueOldId = $ue->getOldId();
            if (null === $ueOldId) {
                ++$skipped;
                continue;
            }

            $sql = <<<'SQL'
SELECT
    id, ue_id, matiere_parent_id, libelle, libelle_court, description,
    cm_ppn, td_ppn, tp_ppn, cm_formation, td_formation, tp_formation,
    code_matiere, code_element, nb_notes, suspendu, mutualisee,
    coefficient, nb_ects, objectifs_module, competences_visees, contenu, pre_requis, modalites, prolongements, mots_cles, pac, ppn_id, parcours_id
FROM matiere
WHERE ue_id = :ue_id
ORDER BY id
SQL;

            $rows = $this->source->fetchAllAssociative($sql, ['ue_id' => $ueOldId]);
            $snapshot = [];

            foreach ($rows as $row) {
                try {
                    $enseignement = $this->findSnapshotEnseignement(LooseValue::castInt($row['id']), $ue);
                    $isNew = null === $enseignement;
                    $enseignement ??= new ScolEnseignement();

                    $enseignement
                        ->setOldId(LooseValue::castInt($row['id']))
                        ->setLibelle(LooseValue::castString($row['libelle']))
                        ->setLibelleCourt(LooseValue::truthyString($row['libelle_court']))
                        ->setDescription(LooseValue::truthyString($row['description']))
                        ->setObjectif(LooseValue::truthyString($row['objectifs_module']))
                        ->setPreRequis(LooseValue::truthyString($row['pre_requis']))
                        ->setMotsCles(LooseValue::truthyString($row['mots_cles']))
                        ->setCodeEnseignement(LooseValue::truthyString($row['code_matiere']))
                        ->setCodeApogee(LooseValue::truthyString($row['code_element']))
                        ->setSuspendu((bool) $row['suspendu'])
                        ->setMutualisee((bool) $row['mutualisee'])
                        ->setNbNotes(LooseValue::castInt($row['nb_notes']))
                        ->setType(TypeEnseignementEnum::TYPE_MATIERE)
                        ->setHeures([
                            'CM' => ['PN' => LooseValue::castFloat($row['cm_ppn']), 'IUT' => LooseValue::castFloat($row['cm_formation'])],
                            'TD' => ['PN' => LooseValue::castFloat($row['td_ppn']), 'IUT' => LooseValue::castFloat($row['td_formation'])],
                            'TP' => ['PN' => LooseValue::castFloat($row['tp_ppn']), 'IUT' => LooseValue::castFloat($row['tp_formation'])],
                            'Projet' => ['PN' => 0, 'IUT' => 0],
                        ])
                        ->setOpt([
                            'competences_visees' => LooseValue::truthyString($row['competences_visees']),
                            'contenu' => LooseValue::truthyString($row['contenu']),
                            'modalites' => LooseValue::truthyString($row['modalites']),
                            'prolongements' => LooseValue::truthyString($row['prolongements']),
                            'pac' => (bool) $row['pac'],
                            'ppn_old_id' => null !== $row['ppn_id'] ? LooseValue::castInt($row['ppn_id']) : null,
                            'parcours_old_id' => null !== $row['parcours_id'] ? LooseValue::castInt($row['parcours_id']) : null,
                        ]);

                    if ($isNew) {
                        $this->entityManager->persist($enseignement);
                        ++$created;
                    } else {
                        ++$updated;
                    }

                    $link = $this->entityManager->getRepository(ScolEnseignementUe::class)->findOneBy([
                        'enseignement' => $enseignement,
                        'ue' => $ue,
                    ]);
                    if (null === $link) {
                        $link = new ScolEnseignementUe($enseignement, $ue);
                        $this->entityManager->persist($link);
                    }
                    $link
                        ->setCoefficient(LooseValue::castFloat($row['coefficient']))
                        ->setEcts(LooseValue::castFloat($row['nb_ects']));

                    $snapshot[LooseValue::castInt($row['id'])] = $enseignement;
                } catch (\Throwable $e) {
                    ++$failed;
                    if (count($messages) < 20) {
                        $messages[] = sprintf('Matière V3 #%s / UE V3 #%s: %s', LooseValue::nullableString($row['id']), LooseValue::nullableString($ueOldId), $e->getMessage());
                    }
                }
            }

            // Les relations parent/enfant sont résolues dans le même snapshot annuel.
            foreach ($rows as $row) {
                if (null === $row['matiere_parent_id']) {
                    continue;
                }

                $child = $snapshot[LooseValue::castInt($row['id'])] ?? null;
                $parent = $snapshot[LooseValue::castInt($row['matiere_parent_id'])] ?? $this->findSnapshotEnseignement(LooseValue::castInt($row['matiere_parent_id']), $ue);
                if (null !== $child && null !== $parent) {
                    $child->setParent($parent);
                }
            }

            $this->flush($context);
        }

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }

    private function findSnapshotEnseignement(int $oldId, StructureUe $ue): ?ScolEnseignement
    {
        return LooseValue::nullableInstance($this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(ScolEnseignement::class, 'e')
            ->innerJoin('e.enseignementUes', 'eu')
            ->andWhere('e.oldId = :oldId')
            ->andWhere('eu.ue = :ue')
            ->setParameter('oldId', $oldId)
            ->setParameter('ue', $ue)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(), ScolEnseignement::class);
    }
}
