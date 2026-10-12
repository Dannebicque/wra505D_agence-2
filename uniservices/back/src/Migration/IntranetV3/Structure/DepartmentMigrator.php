<?php

namespace App\Migration\IntranetV3\Structure;

use App\Entity\Structure\StructureDepartement;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;

final class DepartmentMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'departements';
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $rows = $this->source->fetchAllAssociative(
            'SELECT id, libelle, logo_name, tel_contact, couleur, site_web, description, actif, opt_materiel, opt_edt, opt_stage, respri_id FROM departement ORDER BY id'
        );
        $repository = $this->entityManager->getRepository(StructureDepartement::class);
        $created = $updated = $failed = 0;
        $messages = [];

        foreach ($rows as $row) {
            try {
                $entity = $repository->findOneBy(['oldId' => LooseValue::castInt($row['id'])]);
                $isNew = null === $entity;
                $entity ??= new StructureDepartement();

                $entity
                    ->setUuid($this->resolveUuid($row['uuid'] ?? null))
                    ->setOldId(LooseValue::castInt($row['id']))
                    ->setLibelle(LooseValue::castString($row['libelle']))
                    ->setLogoName(LooseValue::truthyString($row['logo_name']))
                    ->setTelContact(LooseValue::truthyString($row['tel_contact']))
                    ->setCouleur(LooseValue::truthyString($row['couleur']))
                    ->setSiteWeb(LooseValue::truthyString($row['site_web']))
                    ->setDescription(LooseValue::truthyString($row['description']))
                    ->setActif((bool) $row['actif'])
                    ->setOpt([
                        'materiel' => (bool) $row['opt_materiel'],
                        'edt' => (bool) $row['opt_edt'],
                        'stage' => (bool) $row['opt_stage'],
                        'resp_ri' => null !== $row['respri_id'] ? LooseValue::castString($row['respri_id']) : '',
                    ]);

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                $messages[] = sprintf('Departement #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage());
            }
        }

        $this->flush($context);

        return new MigrationResult($created, $updated, 0, $failed, $messages);
    }
}
