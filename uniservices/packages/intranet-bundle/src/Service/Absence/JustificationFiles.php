<?php

declare(strict_types=1);

namespace IntranetBundle\Service\Absence;

use IntranetBundle\Entity\Etudiant\EtudiantAbsenceJustificatif;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Pièces des justificatifs d'absence, rangées hors de public/ sous l'uuid du justificatif : souvent
 * médicales, elles ne se lisent qu'à travers le contrôle d'accès de l'API.
 */
final readonly class JustificationFiles
{
    public const MAX_SIZE = 5 * 1024 * 1024;

    /** Types acceptés, d'après le contenu du fichier et non d'après ce qu'annonce le navigateur. */
    public const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/justificatifs')]
        private string $storageDir,
    ) {
    }

    public function store(UploadedFile $file, EtudiantAbsenceJustificatif $justification): void
    {
        $file->move($this->storageDir, $justification->getUuidString());
    }

    public function path(EtudiantAbsenceJustificatif $justification): ?string
    {
        $path = $this->storageDir.'/'.$justification->getUuidString();

        return is_file($path) ? $path : null;
    }
}
