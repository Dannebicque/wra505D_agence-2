<?php

namespace DocumentBundle\Migration\IntranetV3;

use App\Utils\LooseValue;
use App\Migration\IntranetV3\AbstractMigrator;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use DocumentBundle\Entity\Document;
use DocumentBundle\Entity\DocumentCategory;

final class DocumentMigrator extends AbstractMigrator
{
    public function getName(): string
    {
        return 'documents';
    }
    public function getDependencies(): array
    {
        return [DocumentCategoryMigrator::class];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $created = $updated = $skipped = $failed = $processed = $missingCategory = $unknownVisibility = 0;
        $messages = [];
        $total = LooseValue::castInt($this->source->fetchOne('SELECT COUNT(*) FROM document'));
        $this->startProgress($context, 'Documents', $total);

        $sql = 'SELECT id, taille, type_fichier, type_document_id, description, libelle, document_name, type_destinataire, created, updated FROM document ORDER BY id';
        foreach ($this->source->executeQuery($sql)->iterateAssociative() as $row) {
            try {
                $entity = $this->entityManager->getRepository(Document::class)->findOneBy(['oldId' => LooseValue::castInt($row['id'])]);
                $isNew = null === $entity;
                $entity ??= new Document();

                $mime = LooseValue::truthyString(trim(LooseValue::castString($row['type_fichier'] ?? ''))) ?? 'application/octet-stream';
                $filename = trim(LooseValue::castString($row['document_name'] ?? ''));
                if ('' === $filename) {
                    ++$skipped;
                    if (count($messages) < 20) {
                        $messages[] = sprintf('Document #%s ignoré : document_name vide.', LooseValue::nullableString($row['id']));
                    }
                    $context->advanceProgress();
                    ++$processed;
                    continue;
                }

                $category = null;
                if (null !== $row['type_document_id']) {
                    $category = $this->entityManager->getRepository(DocumentCategory::class)->findOneBy(['oldId' => LooseValue::castInt($row['type_document_id'])]);
                    if (null === $category) {
                        ++$missingCategory;
                    }
                }

                $visibility = match ($row['type_destinataire'] ?? null) {
                    'ETU' => 'ETUDIANT',
                    'PERS' => 'PERSONNEL',
                    null, '' => 'PUBLIC',
                    default => null,
                };
                if (null === $visibility) {
                    ++$unknownVisibility;
                    ++$skipped;
                    if (count($messages) < 20) {
                        $messages[] = sprintf(
                            'Document #%s ignoré : type_destinataire V3 inconnu "%s".',
                            LooseValue::nullableString($row['id']),
                            LooseValue::nullableString($row['type_destinataire']),
                        );
                    }
                    $context->advanceProgress();
                    ++$processed;
                    continue;
                }

                $entity->setOldId(LooseValue::castInt($row['id']))
                    ->setTitre(LooseValue::truthyString($row['libelle']) ?? $filename)
                    ->setDescription(LooseValue::nullableString($row['description']))
                    ->setFilename($filename)
                    ->setMimeType($mime)
                    ->setFileSize(max(0, (int) round(LooseValue::castFloat($row['taille'] ?? 0))))
                    ->setType(self::typeFromMime($mime))
                    ->setVisibility($visibility)
                    ->setCategory($category)
                    ->setDepartement($category?->getDepartement());

                if (LooseValue::truthyString($row['created']) !== null) {
                    $entity->setCreatedAt(new \DateTimeImmutable(LooseValue::castString($row['created'])));
                }
                if (LooseValue::truthyString($row['updated']) !== null) {
                    $entity->setUpdatedAt(new \DateTimeImmutable(LooseValue::castString($row['updated'])));
                } elseif (LooseValue::truthyString($row['created']) !== null) {
                    $entity->setUpdatedAt(new \DateTimeImmutable(LooseValue::castString($row['created'])));
                }

                if ($isNew) {
                    $this->entityManager->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }
            } catch (\Throwable $e) {
                ++$failed;
                if (count($messages) < 20) {
                    $messages[] = sprintf('Document #%s: %s', LooseValue::nullableString($row['id']), $e->getMessage());
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

        if ($missingCategory > 0) {
            $messages[] = sprintf('%d document(s) avec une catégorie V3 introuvable ont été importés sans catégorie.', $missingCategory);
        }
        if ($unknownVisibility > 0) {
            $messages[] = sprintf('%d document(s) avec un typeDestinataire V3 inconnu ont été ignorés pour éviter une exposition PUBLIC accidentelle.', $unknownVisibility);
        }
        $messages[] = 'Les relations V3 document↔semestres et les favoris ne sont pas migrés : le modèle Document actuel ne possède pas d’équivalent direct.';
        $messages[] = 'Cette étape migre les métadonnées. Les fichiers physiques sont contrôlés séparément.';

        return new MigrationResult($created, $updated, $skipped, $failed, $messages);
    }

    private static function typeFromMime(string $mime): string
    {
        return match (true) {
            'application/pdf' === $mime => 'pdf',
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            str_contains($mime, 'spreadsheet'), str_contains($mime, 'excel') => 'excel',
            str_contains($mime, 'presentation'), str_contains($mime, 'powerpoint') => 'powerpoint',
            str_contains($mime, 'word'), str_contains($mime, 'opendocument.text') => 'word',
            str_starts_with($mime, 'text/') => 'text',
            str_contains($mime, 'zip'), str_contains($mime, 'archive'), str_contains($mime, 'compressed') => 'archive',
            default => 'file',
        };
    }
}
