<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\EtablissementRepository;
use App\State\Processor\EtablissementProcessor;
use App\State\Provider\EtablissementProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: EtablissementRepository::class)]
#[UniqueEntity(fields: ['isMain'], message: 'Il ne peut y avoir qu\'un seul établissement principal.')]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['etablissement:read']],
            provider: EtablissementProvider::class,
            processor: EtablissementProcessor::class
        ),
        // Réglages des intégrations, clés d'API masquées : réservés à qui peut modifier
        // l'établissement, puisque la collection reste lisible sans connexion.
        new Get(
            uriTemplate: '/etablissements/{id}/settings',
            normalizationContext: ['groups' => ['etablissement:settings']],
            security: "is_granted('CAN_EDIT_ETABLISSEMENT', object)",
        ),
        new Patch(
            inputFormats: [
                'json' => ['application/merge-patch+json']
            ],
            denormalizationContext: ['groups' => ['etablissement:write']],
            securityPostDenormalize: "is_granted('CAN_EDIT_ETABLISSEMENT', object)",
            processor: EtablissementProcessor::class
        ),
        new Post(
            uriTemplate: '/etablissements/{id}/logo',
            inputFormats: [
                'multipart' => ['multipart/form-data']
            ],
            security: "is_granted('CAN_EDIT_ETABLISSEMENT', object)",
            deserialize: false,
            processor: EtablissementProcessor::class
        ),
        new Post(
            denormalizationContext: ['groups' => ['etablissement:write']],
            securityPostDenormalize: "is_granted('CAN_EDIT_ETABLISSEMENT', object)",
            processor: EtablissementProcessor::class
        )

    ],
)]
class Etablissement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['etablissement:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['etablissement:read', 'etablissement:write'])]
    private ?string $libelle = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['etablissement:read', 'etablissement:write'])]
    private ?string $logo_name = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    #[Groups(['etablissement:read', 'etablissement:write'])]
    private ?array $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['etablissement:read', 'etablissement:write'])]
    private ?string $site_web = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isMain = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    /**
     * Intégrations EduSign et ORéBUT, clés d'API comprises : écrites par l'API, jamais lues telles
     * quelles (voir getMaskedSettings()).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['etablissement:write'])]
    private ?array $settings = null;

    private const EDUSIGN_SCOPES = ['FI', 'FC'];

    private const INTEGRATIONS = ['edusign', 'orebut'];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getLogoName(): ?string
    {
        return $this->logo_name;
    }

    public function setLogoName(?string $logo_name): static
    {
        $this->logo_name = $logo_name;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getAdresse(): ?array
    {
        return $this->adresse;
    }

    /** @param array<string, mixed>|null $adresse */
    public function setAdresse(?array $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getSiteWeb(): ?string
    {
        return $this->site_web;
    }

    public function setSiteWeb(?string $site_web): static
    {
        $this->site_web = $site_web;

        return $this;
    }

    public function getIsMain(): ?bool
    {
        return $this->isMain;
    }

    public function setIsMain(?bool $isMain): void
    {
        $this->isMain = $isMain;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * @return array{integrations: array<string, array{enabled: bool, scope: list<string>, apiKey: ?string, apiUrl: ?string}>}
     */
    public function getSettings(): array
    {
        return self::normalizeSettings($this->settings ?? []);
    }

    /**
     * Une clé d'API absente ou vide conserve la clé enregistrée : la lecture ne la rend jamais, un
     * formulaire qui renvoie les réglages ne doit pas l'effacer.
     *
     * @param array<string, mixed>|null $settings
     */
    public function setSettings(?array $settings): static
    {
        $current = $this->getSettings();
        $next = self::normalizeSettings($settings ?? []);
        foreach (self::INTEGRATIONS as $name) {
            if ($next['integrations'][$name]['apiKey'] === null) {
                $next['integrations'][$name]['apiKey'] = $current['integrations'][$name]['apiKey'];
            }
        }
        $this->settings = $next;

        return $this;
    }

    /**
     * Réglages lisibles par l'administration : chaque clé d'API remplacée par l'indication qu'elle
     * existe.
     *
     * @return array{integrations: array<string, array{enabled: bool, scope: list<string>, apiKey: null, apiKeySet: bool, apiUrl: ?string}>}
     */
    #[Groups(['etablissement:settings'])]
    #[SerializedName('settings')]
    public function getMaskedSettings(): array
    {
        $integrations = [];
        foreach ($this->getSettings()['integrations'] as $name => $integration) {
            $integrations[$name] = [...$integration, 'apiKey' => null, 'apiKeySet' => $integration['apiKey'] !== null];
        }

        return ['integrations' => $integrations];
    }

    /**
     * Ce que l'intranet affiche sans connexion : quelles intégrations sont actives, et pour quelles
     * formations. Ni clé, ni URL.
     *
     * @return array<string, array{enabled: bool, scope: list<string>}>
     */
    #[Groups(['etablissement:read'])]
    public function getIntegrations(): array
    {
        $integrations = [];
        foreach ($this->getSettings()['integrations'] as $name => $integration) {
            $integrations[$name] = ['enabled' => $integration['enabled'], 'scope' => $integration['scope']];
        }

        return $integrations;
    }

    /**
     * @param array<array-key, mixed> $settings
     *
     * @return array{integrations: array<string, array{enabled: bool, scope: list<string>, apiKey: ?string, apiUrl: ?string}>}
     */
    private static function normalizeSettings(array $settings): array
    {
        $given = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
        $integrations = [];
        foreach (self::INTEGRATIONS as $name) {
            $integration = is_array($given[$name] ?? null) ? $given[$name] : [];
            $scope = is_array($integration['scope'] ?? null) ? $integration['scope'] : [];
            $integrations[$name] = [
                'enabled' => ($integration['enabled'] ?? false) === true,
                'scope' => $name === 'edusign' ? array_values(array_filter(self::EDUSIGN_SCOPES, static fn (string $code): bool => in_array($code, $scope, true))) : [],
                'apiKey' => self::nonEmptyString($integration['apiKey'] ?? null),
                'apiUrl' => self::nonEmptyString($integration['apiUrl'] ?? null),
            ];
        }

        return ['integrations' => $integrations];
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
