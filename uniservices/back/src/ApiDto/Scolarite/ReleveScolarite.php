<?php

namespace App\ApiDto\Scolarite;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Scolarite\ReleveScolariteProvider;

/**
 * Relevé de l'année en cours de l'étudiant connecté. L'adresse ne porte aucun identifiant : on ne
 * peut demander que le sien.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/scolarite',
            uriVariables: [],
            openapi: new Operation(
                summary: 'Notes publiées, moyennes provisoires et absences de l\'étudiant connecté, pour l\'année en cours',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: ReleveScolariteProvider::class,
        ),
    ],
)]
final class ReleveScolarite
{
    /**
     * @param list<array<string, mixed>> $semestres
     */
    public function __construct(
        private readonly ?string $anneeUniversitaire,
        private readonly array $semestres,
    ) {
    }

    public function getAnneeUniversitaire(): ?string
    {
        return $this->anneeUniversitaire;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSemestres(): array
    {
        return $this->semestres;
    }
}
