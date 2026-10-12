<?php

namespace App\Migration\IntranetV3\Users;

use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Users\Personnel;
use App\Enum\StatutEnum;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Migration\IntranetV3\Structure\AcademicYearMigrator;
use App\Utils\LooseValue;

final class StaffMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'personnels';
    }

    public function getDependencies(): array
    {
        return [AcademicYearMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $failed = $processed = 0;
        $messages = [];
        $unknownStatuses = [];
        $deletedCount = 0;

        $sql = <<<'SQL'
SELECT
    id, username, mail_univ, mail_perso, prenom, nom, photo_name, annee_universitaire_id,
    statut, poste_interne, tel_bureau, responsabilites, domaines, entreprise,
    bureau1, bureau2, numero_harpege, initiales, nb_heures_service,
    site_perso, site_univ, id_edu_sign, deleted
FROM personnel
ORDER BY id
SQL;
        $rows = $this->source->executeQuery($sql)->iterateAssociative();

        foreach ($rows as $row) {
            try {
                $repository = $this->entityManager->getRepository(Personnel::class);
                $anneeRepository = $this->entityManager->getRepository(StructureAnneeUniversitaire::class);

                $entity = $repository->findOneBy(['oldId' => LooseValue::castInt($row['id'])]);
                $isNew = null === $entity;
                $entity ??= new Personnel();

                $entity
                    ->setOldId(LooseValue::castInt($row['id']))
                    ->setUsername(LooseValue::castString($row['username']))
                    ->setMailUniv(LooseValue::castString($row['mail_univ']))
                    ->setPrenom(LooseValue::castString($row['prenom']))
                    ->setNom(LooseValue::castString($row['nom']))
                    ->setPhotoName(LooseValue::nullableString($row['photo_name']))
                    ->setPosteInterne(LooseValue::nullableString($row['poste_interne']))
                    ->setTelBureau(LooseValue::nullableString($row['tel_bureau']))
                    ->setResponsabilites(LooseValue::nullableString($row['responsabilites']))
                    ->setEntreprise(LooseValue::nullableString($row['entreprise']))
                    ->setBureau($this->mergeBureaux($row['bureau1'], $row['bureau2']))
                    ->setNumeroHarpege(null !== $row['numero_harpege'] && '' !== trim(LooseValue::castString($row['numero_harpege'])) ? LooseValue::castInt($row['numero_harpege']) : null)
                    ->setInitiales(null !== $row['initiales'] && '' !== trim(LooseValue::castString($row['initiales'])) ? mb_substr(trim(LooseValue::castString($row['initiales'])), 0, 10) : null)
                    ->setNbHeuresService(null !== $row['nb_heures_service'] ? (int) round(LooseValue::castFloat($row['nb_heures_service'])) : null)
                    ->setSitePerso(LooseValue::nullableString($row['site_perso']))
                    ->setSiteUniv(LooseValue::nullableString($row['site_univ']))
                    ->setDomaines($this->legacyTextToArray($row['domaines']));

                $statut = $this->mapStatut($row['statut']);
                if (null !== $statut) {
                    $entity->setStatut($statut);
                } elseif (null !== $row['statut'] && '' !== trim(LooseValue::castString($row['statut']))) {
                    $unknownStatuses[LooseValue::castString($row['statut'])] = ($unknownStatuses[LooseValue::castString($row['statut'])] ?? 0) + 1;
                }

                if (null !== $row['id_edu_sign'] && '' !== trim(LooseValue::castString($row['id_edu_sign']))) {
                    $decodedEduSign = json_decode(LooseValue::castString($row['id_edu_sign']), true);
                    if (is_array($decodedEduSign)) {
                        $entity->setIdEduSign(LooseValue::assoc($decodedEduSign));
                    }
                }

                if ((bool) $row['deleted']) {
                    ++$deletedCount;
                }

                $entity->setMailPerso(LooseValue::nullableString($row['mail_perso']));

                if (null !== $row['annee_universitaire_id']) {
                    $entity->setAnneeUniversitaire($anneeRepository->findOneBy(['oldId' => LooseValue::castInt($row['annee_universitaire_id'])]));
                }

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                $messages[] = sprintf('Personnel #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage());
            }

            ++$processed;
            $this->flushBatch($context, $processed);
        }

        $this->flushAndClear($context);

        if ([] !== $unknownStatuses) {
            ksort($unknownStatuses);
            $messages[] = 'Statuts V3 non reconnus et laissés à null: '.implode(', ', array_map(
                static fn (string $status, int $count): string => sprintf('%s=%d', $status, $count),
                array_keys($unknownStatuses),
                array_values($unknownStatuses),
            )).'.';
        }

        if ($deletedCount > 0) {
            $messages[] = sprintf(
                'Personnels V3 marqués deleted=true: %d. La V4 ne possède pas de champ deleted; ils sont importés pour préserver les références historiques. Leur politique d’accès doit être traitée explicitement.',
                $deletedCount,
            );
        }

        return new MigrationResult($created, $updated, 0, $failed, $messages);
    }

    private function mapStatut(mixed $value): ?StatutEnum
    {
        if (null === $value || '' === trim(LooseValue::castString($value))) {
            return null;
        }

        $value = trim(LooseValue::castString($value));

        return StatutEnum::tryFrom($value) ?? match (mb_strtolower($value)) {
            'permanent' => StatutEnum::AUTRE,
            default => null,
        };
    }

    /** @return list<string>|null */
    private function legacyTextToArray(mixed $value): ?array
    {
        if (null === $value || '' === trim(LooseValue::castString($value))) {
            return null;
        }

        $decoded = json_decode(LooseValue::castString($value), true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(LooseValue::castString(...), $decoded), static fn (string $item): bool => '' !== trim($item)));
        }

        return [trim(LooseValue::castString($value))];
    }

    private function mergeBureaux(mixed $bureau1, mixed $bureau2): ?string
    {
        $parts = array_values(array_filter(
            [null !== $bureau1 ? trim(LooseValue::castString($bureau1)) : '', null !== $bureau2 ? trim(LooseValue::castString($bureau2)) : ''],
            static fn (string $value): bool => '' !== $value,
        ));

        if ([] === $parts) {
            return null;
        }

        return mb_substr(implode(' / ', array_unique($parts)), 0, 100);
    }
}
