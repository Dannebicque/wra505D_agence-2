<?php

declare(strict_types=1);

namespace App\ApiDto\Timetable;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Timetable\StudentWeeksProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Semaines de l'année de l'étudiant connecté et leur charge de cours, pour le sélecteur de
 * semaines de son emploi du temps.
 */
#[ApiResource(
    shortName: 'EmploiDuTempsSemaines',
    operations: [
        new Get(
            uriTemplate: '/me/emploi-du-temps/semaines',
            uriVariables: [],
            openapi: new Operation(
                summary: 'Semaines de l\'année de l\'étudiant connecté, avec leurs minutes de cours',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: StudentWeeksProvider::class,
        ),
    ],
)]
final class StudentWeeks
{
    /**
     * @param list<array{lundi: string, semaineFormation: int|null, minutes: int}> $weeks
     */
    public function __construct(
        #[SerializedName('semaines')]
        private readonly array $weeks,
    ) {
    }

    /**
     * @return list<array{lundi: string, semaineFormation: int|null, minutes: int}>
     */
    public function getWeeks(): array
    {
        return $this->weeks;
    }
}
