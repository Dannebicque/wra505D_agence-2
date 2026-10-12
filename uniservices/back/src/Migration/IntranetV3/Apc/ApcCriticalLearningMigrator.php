<?php

namespace App\Migration\IntranetV3\Apc;

use App\Entity\Apc\ApcApprentissageCritique;
use App\Entity\Apc\ApcCompetence;
use App\Entity\Apc\ApcNiveau;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;

final class ApcCriticalLearningMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'apc-apprentissages-critiques';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [ApcLevelMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = $processed = 0;
        $messages = [];

        $sql = <<<'SQL'
SELECT ac.id, ac.niveau_id, ac.libelle, ac.code, n.competence_id, n.ordre AS niveau_ordre
FROM apc_apprentissage_critique ac
INNER JOIN apc_niveau n ON n.id = ac.niveau_id
ORDER BY ac.id
SQL;

        foreach ($this->source->executeQuery($sql)->iterateAssociative() as $row) {
            try {
                $competence = $this->entityManager->getRepository(ApcCompetence::class)
                    ->findOneBy(['oldId' => LooseValue::castInt($row['competence_id'])]);

                $niveau = null !== $competence
                    ? $this->entityManager->getRepository(ApcNiveau::class)->findOneBy([
                        'competence' => $competence,
                        'ordre' => LooseValue::castInt($row['niveau_ordre']),
                    ])
                    : null;

                if (null === $niveau) {
                    ++$skipped;
                    if (count($messages) < 20) {
                        $messages[] = sprintf('Apprentissage critique V3 #%s ignoré: niveau V3 #%s non résolu.', LooseValue::nullableString($row['id']), LooseValue::nullableString($row['niveau_id']));
                    }
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $entity = $this->entityManager->getRepository(ApcApprentissageCritique::class)->findOneBy([
                    'oldId' => LooseValue::castInt($row['id']),
                ]);
                $isNew = null === $entity;
                $entity ??= new ApcApprentissageCritique($niveau);

                $entity
                    ->setOldId(LooseValue::castInt($row['id']))
                    ->setNiveau($niveau)
                    ->setLibelle(LooseValue::castString($row['libelle']))
                    ->setCode(LooseValue::truthyString($row['code']));

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                if (count($messages) < 20) {
                    $messages[] = sprintf('Apprentissage critique V3 #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage());
                }
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }
}
