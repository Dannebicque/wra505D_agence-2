<?php

declare(strict_types=1);

namespace App\ApiDto\Subject;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Subject\SubjectSheetProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Fiche d'une matière ou d'une SAÉ de l'étudiant connecté, pour l'année en cours. L'identifiant
 * est celui de la matière : une matière qu'il ne suit pas cette année n'existe pas pour lui.
 */
#[ApiResource(
    shortName: 'FicheMatiere',
    operations: [
        new Get(
            uriTemplate: '/me/matieres/{id}',
            uriVariables: ['id'],
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Prochains cours, notes, absences et documents d\'une matière de l\'étudiant connecté',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: SubjectSheetProvider::class,
        ),
    ],
)]
final class SubjectSheet
{
    /**
     * @param array<string, mixed>          $subject
     * @param array<string, mixed>          $grades
     * @param list<array<array-key, mixed>> $absences
     * @param list<array<string, mixed>>    $upcomingCourses
     * @param list<array<string, mixed>>    $documents
     */
    public function __construct(
        #[SerializedName('matiere')]
        private readonly array $subject,
        #[SerializedName('notes')]
        private readonly array $grades,
        private readonly array $absences,
        #[SerializedName('prochainsCours')]
        private readonly array $upcomingCourses,
        private readonly array $documents,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubject(): array
    {
        return $this->subject;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGrades(): array
    {
        return $this->grades;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function getAbsences(): array
    {
        return $this->absences;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUpcomingCourses(): array
    {
        return $this->upcomingCourses;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }
}
