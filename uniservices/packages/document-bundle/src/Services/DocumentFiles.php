<?php

declare(strict_types=1);

namespace DocumentBundle\Services;

use DocumentBundle\Entity\Document;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Contenu des documents, rangé hors de public/ : il ne se lit qu'à travers le contrôle d'accès
 * de l'API, jamais par une adresse directe.
 */
final readonly class DocumentFiles
{
    /**
     * Types servis en ligne pour l'aperçu. Tout autre type, HTML et SVG compris, part en
     * téléchargement : servi en ligne depuis l'origine de l'API, il pourrait y exécuter un script.
     */
    private const INLINE_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(
        #[Autowire('%document_storage_dir%')]
        private string $storageDir,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Chemin du fichier du document, ou null s'il n'a pas de contenu stocké.
     */
    public function path(Document $document): ?string
    {
        $filename = $document->getFilename();
        if (null === $filename) {
            return null;
        }

        $path = $this->pathFor($filename);

        return is_file($path) ? $path : null;
    }

    /**
     * Copie un fichier dans le stockage, sous le nom enregistré dans le document.
     */
    public function store(string $source, string $filename): void
    {
        $this->filesystem->copy($source, $this->pathFor($filename), true);
    }

    public function isInline(string $mimeType): bool
    {
        return in_array($mimeType, self::INLINE_MIME_TYPES, true);
    }

    /**
     * basename() écarte tout segment de chemin : un nom comme ../../.env reste dans le stockage.
     */
    private function pathFor(string $filename): string
    {
        return $this->storageDir.'/'.basename($filename);
    }
}
