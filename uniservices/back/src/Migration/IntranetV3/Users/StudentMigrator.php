<?php

namespace App\Migration\IntranetV3\Users;

use App\Entity\Scolarite\ScolBac;
use App\Entity\Users\Etudiant;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Migration\IntranetV3\Enrolment\BaccalaureateMigrator;
use App\Utils\LooseValue;

final class StudentMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'etudiants';
    }

    public function getDependencies(): array
    {
        return [BaccalaureateMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $failed = $processed = 0;
        $messages = [];
        $deletedCount = 0;

        $sql = <<<'SQL'
SELECT
    e.id, e.username, e.mail_univ, e.mail_perso, e.prenom, e.nom, e.photo_name,
    e.num_etudiant, e.num_ine, e.annee_bac, e.boursier, e.amenagements_particuliers,
    e.promotion, e.annee_sortie, e.bac_id, e.id_edu_sign, e.deleted,
    e.demandeur_emploi, e.login_specifique, e.formation_continue,
    e.intitule_securite_sociale, e.adresse_securite_sociale,
    e.date_naissance, e.tel1, e.tel2, e.lieu_naissance, e.site_perso, e.site_univ,
    ae.adresse1 AS adresse_etudiante_1, ae.adresse2 AS adresse_etudiante_2,
    ae.adresse3 AS adresse_etudiante_3, ae.code_postal AS adresse_etudiante_cp,
    ae.ville AS adresse_etudiante_ville, ae.pays AS adresse_etudiante_pays,
    ap.adresse1 AS adresse_parentale_1, ap.adresse2 AS adresse_parentale_2,
    ap.adresse3 AS adresse_parentale_3, ap.code_postal AS adresse_parentale_cp,
    ap.ville AS adresse_parentale_ville, ap.pays AS adresse_parentale_pays
FROM etudiant e
LEFT JOIN adresse ae ON ae.id = e.adresse_id
LEFT JOIN adresse ap ON ap.id = e.adresse_parentale_id
ORDER BY e.id
SQL;
        $rows = $this->source->executeQuery($sql)->iterateAssociative();

        foreach ($rows as $row) {
            try {
                $repository = $this->entityManager->getRepository(Etudiant::class);
                $bacRepository = $this->entityManager->getRepository(ScolBac::class);

                $entity = $repository->findOneBy(['oldId' => LooseValue::castInt($row['id'])]);
                $isNew = null === $entity;
                $entity ??= new Etudiant();

                $entity
                    ->setOldId(LooseValue::castInt($row['id']))
                    ->setUsername(LooseValue::castString($row['username']))
                    ->setMailUniv(LooseValue::castString($row['mail_univ']))
                    ->setPrenom(LooseValue::castString($row['prenom']))
                    ->setNom(LooseValue::castString($row['nom']))
                    ->setPhotoName(LooseValue::nullableString($row['photo_name']))
                    ->setNumEtudiant(LooseValue::nullableString($row['num_etudiant']))
                    ->setNumIne(LooseValue::nullableString($row['num_ine']))
                    ->setAnneeBac(null !== $row['annee_bac'] ? LooseValue::castInt($row['annee_bac']) : null)
                    ->setBoursier((bool) $row['boursier'])
                    ->setAmenagementsParticuliers(LooseValue::nullableString($row['amenagements_particuliers']))
                    ->setPromotion(null !== $row['promotion'] ? LooseValue::castInt($row['promotion']) : null)
                    ->setAnneeSortie(null !== $row['annee_sortie'] ? LooseValue::castInt($row['annee_sortie']) : 0)
                    ->setOpt([
                        'demandeur_emploi' => (bool) $row['demandeur_emploi'],
                        'login_specifique' => LooseValue::truthyString($row['login_specifique']),
                        'formation_continue' => (bool) $row['formation_continue'],
                        'intitule_securite_sociale' => LooseValue::truthyString($row['intitule_securite_sociale']),
                        'adresse_securite_sociale' => LooseValue::truthyString($row['adresse_securite_sociale']),
                    ])
                    ->setRoles(['ROLE_ETUDIANT'])
                    ->setMailPerso(LooseValue::nullableString($row['mail_perso']))
                    ->setDateNaissance(null !== $row['date_naissance'] ? new \DateTime(LooseValue::castString($row['date_naissance'])) : null)
                    ->setTel1(LooseValue::nullableString($row['tel1']))
                    ->setTel2(LooseValue::nullableString($row['tel2']))
                    ->setLieuNaissance(LooseValue::nullableString($row['lieu_naissance']))
                    ->setSitePerso(LooseValue::nullableString($row['site_perso']))
                    ->setSiteUniv(LooseValue::nullableString($row['site_univ']))
                    ->setAdresseEtudiante($this->addressFromRow($row, 'adresse_etudiante'))
                    ->setAdresseParentale($this->addressFromRow($row, 'adresse_parentale'));

                if (null !== $row['id_edu_sign'] && '' !== trim(LooseValue::castString($row['id_edu_sign']))) {
                    $entity->setKeyEduSign(LooseValue::castString($row['id_edu_sign']));
                }

                if ((bool) $row['deleted']) {
                    ++$deletedCount;
                }

                if (null !== $row['bac_id']) {
                    $entity->setBac($bacRepository->findOneBy(['oldId' => LooseValue::castInt($row['bac_id'])]));
                }

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                $messages[] = sprintf('Etudiant #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage());
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        if ($deletedCount > 0) {
            $messages[] = sprintf(
                'Etudiants V3 marqués deleted=true: %d. La V4 ne possède pas de champ deleted; ils sont importés pour préserver scolarités, notes et stages. Leur politique d’accès doit être traitée explicitement.',
                $deletedCount,
            );
        }

        return new MigrationResult($created, $updated, 0, $failed, $messages);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>|null
     */
    private function addressFromRow(array $row, string $prefix): ?array
    {
        $address = [
            'adresse' => $row[$prefix.'_1'] ?? null,
            'complement1' => $row[$prefix.'_2'] ?? null,
            'complement2' => $row[$prefix.'_3'] ?? null,
            'codePostal' => $row[$prefix.'_cp'] ?? null,
            'ville' => $row[$prefix.'_ville'] ?? null,
            'pays' => $row[$prefix.'_pays'] ?? 'France',
        ];

        foreach ($address as $key => $value) {
            if ('pays' !== $key && null !== $value && '' !== trim(LooseValue::castString($value))) {
                return $address;
            }
        }

        return null;
    }
}
