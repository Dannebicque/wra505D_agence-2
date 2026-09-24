<?php

namespace App\ApiDto\Recherche;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\Provider\Recherche\RechercheProvider;

/**
 * Un résultat de la recherche universelle, limitée au département de l'utilisateur connecté.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/recherche',
            paginationEnabled: false,
            openapi: new Operation(
                summary: 'Recherche tolérante aux fautes parmi les étudiants, les personnels et les documents du département',
                parameters: [
                    new Parameter(
                        name: 'q',
                        in: 'query',
                        required: true,
                        schema: ['type' => 'string'],
                        description: 'Texte recherché, deux caractères au moins',
                    ),
                ],
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: RechercheProvider::class,
        ),
    ],
)]
final class ResultatRecherche
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        private readonly string $cle,
        private readonly string $type,
        #[ApiProperty(identifier: false)]
        private readonly int $id,
        private readonly string $libelle,
        private readonly ?string $detail,
        private readonly float $score,
    ) {
    }

    /**
     * Identifiant unique parmi les résultats : deux types différents peuvent partager un id.
     */
    public function getCle(): string
    {
        return $this->cle;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getScore(): float
    {
        return round($this->score, 2);
    }
}
