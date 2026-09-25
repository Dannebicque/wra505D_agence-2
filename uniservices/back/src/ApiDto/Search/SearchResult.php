<?php

declare(strict_types=1);

namespace App\ApiDto\Search;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\Provider\Search\SearchProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Un résultat de la recherche universelle, limitée au département de l'utilisateur connecté.
 */
#[ApiResource(
    shortName: 'ResultatRecherche',
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
            provider: SearchProvider::class,
        ),
    ],
)]
final readonly class SearchResult
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[SerializedName('cle')]
        private readonly string $key,
        #[SerializedName('type')]
        private readonly string $type,
        #[ApiProperty(identifier: false)]
        #[SerializedName('id')]
        private readonly int $id,
        #[SerializedName('libelle')]
        private readonly string $label,
        #[SerializedName('detail')]
        private readonly ?string $details,
        #[SerializedName('score')]
        private readonly float $score,
        #[SerializedName('mail')]
        private readonly ?string $email = null,
    ) {
    }

    /**
     * Identifiant unique parmi les résultats : deux types différents peuvent partager un id.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    /**
     * Adresse universitaire d'une personne, pour lui écrire depuis la recherche.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getScore(): float
    {
        return round($this->score, 2);
    }
}
