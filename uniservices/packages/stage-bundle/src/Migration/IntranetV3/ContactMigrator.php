<?php

namespace StageBundle\Migration\IntranetV3;

use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Utils\LooseValue;
use StageBundle\Entity\Stages\StageContact;

final class ContactMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'stage-contacts';
    }
    public function getDependencies(): array
    {
        return [];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = $processed = 0;
        $messages = [];
        $idsSql = <<<'SQL'
SELECT DISTINCT contact_id FROM (
    SELECT se.tuteur_id AS contact_id
    FROM stage_etudiant se
    INNER JOIN stage_periode sp ON sp.id = se.stage_periode_id
    INNER JOIN annee_universitaire au ON au.id = sp.annee_universitaire_id
    WHERE au.active = 1 AND se.tuteur_id IS NOT NULL
    UNION
    SELECT e.responsable_id AS contact_id
    FROM entreprise e
    INNER JOIN stage_etudiant se ON se.entreprise_id = e.id
    INNER JOIN stage_periode sp ON sp.id = se.stage_periode_id
    INNER JOIN annee_universitaire au ON au.id = sp.annee_universitaire_id
    WHERE au.active = 1 AND e.responsable_id IS NOT NULL
) contacts
SQL;
        $total = LooseValue::castInt($this->source->fetchOne('SELECT COUNT(*) FROM ('.$idsSql.') c'));
        $this->startProgress($context, 'Contacts de stage référencés', $total);
        $sql = 'SELECT c.id, c.nom, c.prenom, c.fonction, c.telephone, c.email, c.portable, c.civilite, c.fax FROM contact c INNER JOIN ('.$idsSql.') refs ON refs.contact_id = c.id ORDER BY c.id';

        foreach ($this->source->executeQuery($sql)->iterateAssociative() as $row) {
            try {
                $entity = $this->entityManager->getRepository(StageContact::class)->findOneBy(['oldId' => LooseValue::castInt($row['id'])]);
                $isNew = null === $entity;
                $entity ??= new StageContact();
                $entity->setOldId(LooseValue::castInt($row['id']))
                    ->setNom(LooseValue::nullableString($row['nom']))->setPrenom(LooseValue::nullableString($row['prenom']))->setFonction(LooseValue::nullableString($row['fonction']))
                    ->setTelephone(LooseValue::nullableString($row['telephone']))->setEmail(LooseValue::nullableString($row['email']))->setPortable(LooseValue::nullableString($row['portable']))
                    ->setCivilite(LooseValue::nullableString($row['civilite']))->setFax(LooseValue::nullableString($row['fax']));
                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                if (count($messages) < 20) {
                    $messages[] = sprintf('Contact #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage());
                }
            }
            ++$processed;
            $context->advanceProgress();
            if (0 === $processed % self::BATCH_SIZE) {
                $this->flushAndClear($context);
            }
        }
        $this->flushAndClear($context);
        $this->finishProgress($context);
        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }
}
