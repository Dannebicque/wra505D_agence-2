<?php

declare(strict_types=1);

namespace App\ApiDto\Timetable;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\Provider\Timetable\StudentTimetableProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Semaine d'emploi du temps de l'étudiant connecté. L'adresse ne porte aucun identifiant : on ne
 * peut demander que la sienne.
 */
#[ApiResource(
    shortName: 'EmploiDuTemps',
    operations: [
        new Get(
            uriTemplate: '/me/emploi-du-temps',
            uriVariables: [],
            openapi: new Operation(
                summary: 'Cours de l\'étudiant connecté pour une semaine, et la plage horaire de son année',
                parameters: [
                    new Parameter(
                        name: 'semaine',
                        in: 'query',
                        required: false,
                        schema: ['type' => 'string', 'format' => 'date'],
                        description: 'Un jour de la semaine voulue, AAAA-MM-JJ ; la semaine en cours par défaut',
                    ),
                ],
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: StudentTimetableProvider::class,
        ),
    ],
)]
final class StudentTimetable
{
    /**
     * @param array{debut: string, fin: string} $timeRange
     * @param list<array<string, mixed>>         $courses
     */
    public function __construct(
        #[SerializedName('semaine')]
        private readonly string $week,
        #[SerializedName('semaineFormation')]
        private readonly ?int $trainingWeek,
        #[SerializedName('plage')]
        private readonly array $timeRange,
        #[SerializedName('cours')]
        private readonly array $courses,
    ) {
    }

    public function getWeek(): string
    {
        return $this->week;
    }

    public function getTrainingWeek(): ?int
    {
        return $this->trainingWeek;
    }

    /**
     * @return array{debut: string, fin: string}
     */
    public function getTimeRange(): array
    {
        return $this->timeRange;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCourses(): array
    {
        return $this->courses;
    }
}
