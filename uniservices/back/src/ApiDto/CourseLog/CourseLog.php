<?php

declare(strict_types=1);

namespace App\ApiDto\CourseLog;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\CourseLog\CourseLogProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Cahier de texte de l'étudiant connecté. L'adresse ne porte aucun identifiant : on ne peut
 * demander que le sien.
 */
#[ApiResource(
    shortName: 'CahierDeTexte',
    operations: [
        new Get(
            uriTemplate: '/me/cahier-de-texte',
            uriVariables: [],
            openapi: new Operation(
                summary: 'Évaluations à venir et plans de cours des matières de l\'étudiant connecté, pour l\'année en cours',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: CourseLogProvider::class,
        ),
    ],
)]
final class CourseLog
{
    /**
     * @param list<array<string, mixed>> $deadlines
     * @param list<array<string, mixed>> $subjects
     */
    public function __construct(
        #[SerializedName('anneeUniversitaire')]
        private readonly ?string $academicYear,
        #[SerializedName('echeances')]
        private readonly array $deadlines,
        #[SerializedName('matieres')]
        private readonly array $subjects,
    ) {
    }

    public function getAcademicYear(): ?string
    {
        return $this->academicYear;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDeadlines(): array
    {
        return $this->deadlines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSubjects(): array
    {
        return $this->subjects;
    }
}
