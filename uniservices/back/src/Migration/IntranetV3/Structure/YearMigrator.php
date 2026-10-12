<?php

namespace App\Migration\IntranetV3\Structure;

use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructurePn;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;

final class YearMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'annees';
    }

    /** @return list<class-string<MigratorInterface>> */
    public function getDependencies(): array
    {
        return [NationalProgramMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $repository = $this->entityManager->getRepository(StructureAnnee::class);
        $pnRepository = $this->entityManager->getRepository(StructurePn::class);
        $created = $updated = $skipped = $failed = $processed = 0;
        $messages = [];

        $pnIds = $this->entityManager->createQueryBuilder()
            ->select('pn.id')
            ->from(StructurePn::class, 'pn')
            ->orderBy('pn.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($pnIds as $pnId) {
            $pn = $pnRepository->find(LooseValue::castInt($pnId));
            if (null === $pn) {
                continue;
            }
            $diplomeOldId = $pn->getDiplome()?->getOldId();
            if (null === $diplomeOldId || null === $pn->getAnneeUniversitaire()) {
                ++$skipped;
                continue;
            }

            $sql = <<<'SQL'
SELECT id, diplome_id, libelle, ordre, libelle_long, actif, couleur, code_version, code_etape, opt_alternance
FROM annee
WHERE diplome_id = :diplome_id
ORDER BY ordre, id
SQL;

            foreach ($this->source->iterateAssociative($sql, ['diplome_id' => $diplomeOldId]) as $row) {
                try {
                    $entity = $repository->findOneBy([
                        'oldId' => LooseValue::castInt($row['id']),
                        'pn' => $pn,
                    ]);
                    $isNew = null === $entity;
                    $entity ??= new StructureAnnee();

                    $entity
                        ->setOldId(LooseValue::castInt($row['id']))
                        ->setPn($pn)
                        ->setLibelle(LooseValue::castString($row['libelle']))
                        ->setOrdre(LooseValue::castInt($row['ordre']))
                        ->setLibelleLong(LooseValue::truthyString($row['libelle_long']))
                        ->setActif((bool) $row['actif'])
                        ->setCouleur(LooseValue::truthyString($row['couleur']))
                        ->setApogeeCodeVersion(LooseValue::truthyString($row['code_version']))
                        ->setApogeeCodeEtape(LooseValue::truthyString($row['code_etape']))
                        ->setOpt(['alternance' => (bool) $row['opt_alternance']]);

                    if ($isNew) {
                        $this->entityManager->persist($entity);
                        ++$created;
                    } else {
                        ++$updated;
                    }
                } catch (\Throwable $e) {
                    ++$failed;
                    $messages[] = sprintf(
                        'Annee V3 #%s / PN %s: %s',
                        LooseValue::nullableString($row['id']),
                        $pn->getId() ?? 'new',
                        $e->getMessage(),
                    );
                }

                ++$processed;
            }

            $this->flushAndClear($context);
        }

        $this->flush($context);

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }
}
