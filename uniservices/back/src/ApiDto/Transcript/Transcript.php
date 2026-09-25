<?php

declare(strict_types=1);

namespace App\ApiDto\Transcript;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Transcript\TranscriptProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Relevé de l'année en cours de l'étudiant connecté. L'adresse ne porte aucun identifiant : on ne
 * peut demander que le sien.
 */
#[ApiResource(
    shortName: 'ReleveScolarite',
    operations: [
        new Get(
            uriTemplate: '/me/scolarite',
            uriVariables: [],
            openapi: new Operation(
                summary: 'Notes publiées, moyennes provisoires et absences de l\'étudiant connecté, pour l\'année en cours',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: TranscriptProvider::class,
        ),
    ],
)]
final class Transcript
{
    /**
     * @param list<array<string, mixed>> $semesters
     */
    public function __construct(
        #[SerializedName('anneeUniversitaire')]
        private readonly ?string $academicYear,
        #[SerializedName('semestres')]
        private readonly array $semesters,
    ) {
    }

    public function getAcademicYear(): ?string
    {
        return $this->academicYear;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSemesters(): array
    {
        return $this->semesters;
    }
}
