<?php

declare(strict_types=1);

namespace App\Service\Subject;

use App\ApiDto\Subject\SubjectSheet;
use App\Entity\Users\Etudiant;
use App\Security\DocumentReadAccess;
use App\Service\Timetable\StudentTimetableBuilder;
use App\Service\Transcript\TranscriptBuilder;
use DocumentBundle\Entity\Document;
use DocumentBundle\Repository\DocumentRepository;

/**
 * Fiche d'une matière de l'étudiant : ce que le relevé, l'emploi du temps et les documents
 * disent d'elle, réunis. Chaque partie reprend le calcul de sa page pour que les chiffres ne se
 * contredisent jamais d'une page à l'autre.
 */
final readonly class SubjectSheetBuilder
{
    public const UPCOMING_COURSES = 5;

    public function __construct(
        private TranscriptBuilder $transcriptBuilder,
        private SubjectSheetAssembler $assembler,
        private StudentTimetableBuilder $timetableBuilder,
        private DocumentRepository $documents,
        private DocumentReadAccess $documentReadAccess,
    ) {
    }

    /**
     * Null si la matière n'appartient pas à l'année en cours de l'étudiant.
     */
    public function build(Etudiant $student, int $teachingId): ?SubjectSheet
    {
        $sheet = $this->assembler->assemble($this->transcriptBuilder->build($student)['semestres'], $teachingId);
        if (null === $sheet) {
            return null;
        }

        return new SubjectSheet(
            $sheet['matiere'],
            $sheet['notes'],
            $sheet['absences'],
            $this->timetableBuilder->upcoming($student, $teachingId, self::UPCOMING_COURSES),
            $this->documents($student, $teachingId),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documents(Etudiant $student, int $teachingId): array
    {
        $rows = [];
        foreach ($this->documents->findBy(['enseignement' => $teachingId], ['updatedAt' => 'DESC']) as $document) {
            if (!$this->documentReadAccess->allows($document, $student)) {
                continue;
            }
            $rows[] = $this->document($document);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function document(Document $document): array
    {
        return [
            'id' => $document->getId(),
            'titre' => $document->getTitre(),
            'type' => $document->getType(),
            'mimeType' => $document->getMimeType(),
            'taille' => $document->getFileSize(),
            'modifieLe' => ($document->getUpdatedAt() ?? $document->getCreatedAt())?->format(\DateTimeInterface::ATOM),
        ];
    }
}
