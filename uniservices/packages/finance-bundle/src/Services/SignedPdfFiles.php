<?php

declare(strict_types=1);

namespace FinanceBundle\Services;

use FinanceBundle\Entity\FinanceBonCommande;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * PDF signés des demandes d'achat, rangés hors de public/ : ils ne se lisent qu'à travers le
 * contrôle d'accès de l'API.
 */
final readonly class SignedPdfFiles
{
    public const MAX_SIZE = 10 * 1024 * 1024;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/finance')]
        private string $storageDir,
    ) {
    }

    /** Type lu dans le contenu du fichier, et non dans ce qu'annonce le navigateur. */
    public function isPdf(UploadedFile $file): bool
    {
        return $file->isValid() && $file->getSize() <= self::MAX_SIZE && $file->getMimeType() === 'application/pdf';
    }

    /** Range le fichier et rend le nom à enregistrer sur la demande. */
    public function store(UploadedFile $file, FinanceBonCommande $commande): string
    {
        $fileName = sprintf('demande_%d_signee_%s.pdf', $commande->getId(), bin2hex(random_bytes(8)));
        $file->move($this->storageDir, $fileName);

        return $fileName;
    }

    /** Seul le nom de fichier enregistré compte : un chemin ne sort jamais du dossier de stockage. */
    public function path(FinanceBonCommande $commande): ?string
    {
        $stored = $commande->getPdfFilePath();
        if ($stored === null || $stored === '') {
            return null;
        }

        $path = $this->storageDir.'/'.basename($stored);

        return is_file($path) ? $path : null;
    }
}
