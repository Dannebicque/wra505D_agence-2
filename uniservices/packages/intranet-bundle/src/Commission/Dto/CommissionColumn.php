<?php

namespace IntranetBundle\Commission\Dto;

final readonly class CommissionColumn implements \JsonSerializable
{
    public const TYPE_UE = 'ue';
    public const TYPE_ENSEIGNEMENT = 'enseignement';

    /**
     * @param array<array<string, mixed>>|null $ues Rattachements UE pour les ressources/SAÉ
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public ?string $parentKey = null,
        public ?float $coefficient = null,
        public ?float $ects = null,
        public ?string $sublabel = null,
        public ?string $nature = null,
        public ?int $enseignementId = null,
        public ?array $ues = null,
        public ?string $color = null,
        public ?string $competenceNomCourt = null,
        public ?string $competenceCouleur = null,
    ) {
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     parentKey: string|null,
     *     coefficient: float|null,
     *     ects: float|null,
     *     sublabel: string|null,
     *     nature: string|null,
     *     enseignementId: int|null,
     *     ues: array<array<string, mixed>>|null,
     *     color: string|null,
     *     competenceNomCourt: string|null,
     *     competenceCouleur: string|null
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'parentKey' => $this->parentKey,
            'coefficient' => $this->coefficient,
            'ects' => $this->ects,
            'sublabel' => $this->sublabel,
            'nature' => $this->nature,
            'enseignementId' => $this->enseignementId,
            'ues' => $this->ues,
            'color' => $this->color,
            'competenceNomCourt' => $this->competenceNomCourt,
            'competenceCouleur' => $this->competenceCouleur,
        ];
    }
}
