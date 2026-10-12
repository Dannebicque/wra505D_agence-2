<?php

namespace App\Migration\IntranetV3\Apc;

use App\Entity\Apc\ApcCompetence;
use App\Entity\Structure\StructureUe;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\Curriculum\TeachingUnitMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;

final class ApcTeachingUnitLinkMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'apc-ue-liens';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [ApcCompetencyMigrator::class, TeachingUnitMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = 0;
        $updated = $skipped = $failed = $processed = 0;
        $messages = [];

        $sql = <<<'SQL'
SELECT id, apc_competence_id
FROM ue
WHERE apc_competence_id IS NOT NULL
ORDER BY id
SQL;

        foreach ($this->source->executeQuery($sql)->iterateAssociative() as $row) {
            try {
                $competence = $this->entityManager->getRepository(ApcCompetence::class)
                    ->findOneBy(['oldId' => LooseValue::castInt($row['apc_competence_id'])]);

                if (null === $competence) {
                    ++$skipped;
                    if (count($messages) < 20) {
                        $messages[] = sprintf('UE V3 #%s: compétence APC V3 #%s non résolue.', LooseValue::nullableString($row['id']), LooseValue::nullableString($row['apc_competence_id']));
                    }
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                $ues = $this->entityManager->getRepository(StructureUe::class)->findBy([
                    'oldId' => LooseValue::castInt($row['id']),
                ]);

                if ([] === $ues) {
                    ++$skipped;
                    ++$processed;
                    $this->flushBatch($context, $processed);
                    continue;
                }

                foreach ($ues as $ue) {
                    $ue->setCompetence($competence);
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                if (count($messages) < 20) {
                    $messages[] = sprintf('UE V3 #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage());
                }
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }
}
