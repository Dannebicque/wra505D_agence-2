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
    public function __construct()
    {
        $this->universite = new Universite();
    }

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

    /** Directeur(trice) de l'établissement (prénom et nom). */
    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['etablissement:read', 'etablissement:write'])]
    private ?string $directeurNom = null;

    /** Université de rattachement (colonnes universite_*). */
    #[ORM\Embedded(class: Universite::class, columnPrefix: 'universite_')]
    #[Groups(['etablissement:read', 'etablissement:write'])]
    private Universite $universite;

    /** Signataire des conventions de stage (souvent le directeur, par délégation du président de l'université). */
    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['etablissement:read', 'etablissement:write'])]
    private ?string $signataireNom = null;

    /** Qualité du signataire (ex. « Directeur de l'IUT, par délégation du Président »). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['etablissement:read', 'etablissement:write'])]
    private ?string $signataireQualite = null;

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

    private const GRATIFICATION_MINIMALE = 4.35;

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

    public function getDirecteurNom(): ?string
    {
        return $this->directeurNom;
    }

    public function setDirecteurNom(?string $directeurNom): static
    {
        $this->directeurNom = $directeurNom !== null && trim($directeurNom) !== '' ? trim($directeurNom) : null;

        return $this;
    }

    public function getUniversite(): Universite
    {
        return $this->universite;
    }

    public function setUniversite(Universite $universite): static
    {
        $this->universite = $universite;

        return $this;
    }

    public function getSignataireNom(): ?string
    {
        return $this->signataireNom;
    }

    public function setSignataireNom(?string $signataireNom): static
    {
        $this->signataireNom = $signataireNom !== null && trim($signataireNom) !== '' ? trim($signataireNom) : null;

        return $this;
    }

    public function getSignataireQualite(): ?string
    {
        return $this->signataireQualite;
    }

    public function setSignataireQualite(?string $signataireQualite): static
    {
        $this->signataireQualite = $signataireQualite !== null && trim($signataireQualite) !== '' ? trim($signataireQualite) : null;

        return $this;
    }

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
     * @return array{integrations: array<string, array{enabled: bool, scope: list<string>, apiKey: null, apiKeySet: bool, apiUrl: ?string}>, stage: array{gratification_minimale: float}}
     */
    #[Groups(['etablissement:settings'])]
    #[SerializedName('settings')]
    public function getMaskedSettings(): array
    {
        $integrations = [];
        foreach ($this->getSettings()['integrations'] as $name => $integration) {
            $integrations[$name] = [...$integration, 'apiKey' => null, 'apiKeySet' => $integration['apiKey'] !== null];
        }

        return ['integrations' => $integrations, 'stage' => $this->getSettings()['stage']];
    }

    /**
     * Gratification horaire minimale d'un stage, proposée à l'étudiant qui demande sa convention.
     */
    #[Groups(['etablissement:read'])]
    public function getGratificationMinimale(): float
    {
        return $this->getSettings()['stage']['gratification_minimale'];
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
        $defaults = $this->defaultSettings();

        $resolver = new OptionsResolver();
        $resolver->setDefaults($defaults);
        $resolver->setAllowedTypes('integrations', 'array');
        $resolver->setNormalizer('integrations', function ($options, array $integrations): array {
            $integrationsResolver = new OptionsResolver();
            $integrationsResolver->setDefaults([
                'edusign' => [
                    'enabled' => false,
                    'scope' => [],
                    'apiKey' => null,
                    'apiUrl' => null,
                ],
                'orebut' => [
                    'enabled' => false,
                    'apiUrl' => null,
                ],
            ]);
            $integrationsResolver->setAllowedTypes('edusign', 'array');
            $integrationsResolver->setAllowedTypes('orebut', 'array');
            $resolvedIntegrations = $integrationsResolver->resolve($integrations);

            $edusignResolver = new OptionsResolver();
            $edusignResolver->setDefaults([
                'enabled' => false,
                'scope' => [],
                'apiKey' => null,
                'apiUrl' => null,
            ]);
            $edusignResolver->setAllowedTypes('enabled', 'bool');
            $edusignResolver->setAllowedTypes('scope', 'array');
            $edusignResolver->setAllowedTypes('apiKey', ['null', 'string']);
            $edusignResolver->setAllowedTypes('apiUrl', ['null', 'string']);

            $orebutResolver = new OptionsResolver();
            $orebutResolver->setDefaults([
                'enabled' => false,
                'apiKey' => null,
                'apiUrl' => null,
            ]);
            $orebutResolver->setAllowedTypes('enabled', 'bool');
            $orebutResolver->setAllowedTypes('apiKey', ['null', 'string']);
            $orebutResolver->setAllowedTypes('apiUrl', ['null', 'string']);

            $resolvedIntegrations['edusign'] = $edusignResolver->resolve($resolvedIntegrations['edusign']);
            $resolvedIntegrations['orebut'] = $orebutResolver->resolve($resolvedIntegrations['orebut']);

            return $resolvedIntegrations;
        });

        $resolver->setAllowedTypes('emails', 'array');
        $resolver->setNormalizer('emails', function ($options, array $emails): array {
            $emailsResolver = new OptionsResolver();
            $emailsResolver->setDefaults(self::HABILLAGE_EMAILS_DEFAUT);
            $emailsResolver->setAllowedTypes('couleur', 'string');
            $emailsResolver->setAllowedTypes('expediteur_nom', ['null', 'string']);
            $emailsResolver->setAllowedTypes('reply_to', ['null', 'string']);
            $emailsResolver->setAllowedTypes('signature', 'string');
            $emailsResolver->setAllowedTypes('mentions_legales', 'string');
            $emailsResolver->setAllowedTypes('contact_rgpd', ['null', 'string']);

            return $emailsResolver->resolve($emails);
        });

        $resolver->setAllowedTypes('stage', 'array');
        $resolver->setNormalizer('stage', function ($options, array $stage): array {
            $stageResolver = new OptionsResolver();
            $stageResolver->setDefaults([
                'gratification_minimale' => 4.35,
            ]);
            $stageResolver->setAllowedTypes('gratification_minimale', ['float', 'int']);
            return $stageResolver->resolve($stage);
        });

        return $resolver->resolve($settings);
    }

    /**
     * @param array<array-key, mixed> $settings
     *
     * @return array{integrations: array<string, array{enabled: bool, scope: list<string>, apiKey: ?string, apiUrl: ?string}>, stage: array{gratification_minimale: float}}
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

        $stage = is_array($settings['stage'] ?? null) ? $settings['stage'] : [];
        $gratification = $stage['gratification_minimale'] ?? null;

        return [
            'integrations' => $integrations,
            'stage' => [
                // Taux légal horaire en vigueur, faute de réglage de l'établissement.
                'gratification_minimale' => (is_int($gratification) || is_float($gratification)) && $gratification > 0 ? (float) $gratification : self::GRATIFICATION_MINIMALE,
            ],
            'emails' => self::HABILLAGE_EMAILS_DEFAUT,
        ];
    }

    /**
     * Habillage des e-mails de l'établissement (écran « Habillage des e-mails ») : couleur, expéditeur, signature et
     * mentions imposées dans le pied de page de tous les e-mails (non modifiables par l'éditeur des modèles).
     */
    public const HABILLAGE_EMAILS_DEFAUT = [
        // Les clients de messagerie ne lisent pas les variables CSS : la primaire VIOLET_IUT en clair.
        'couleur' => '#4D3677',
        'expediteur_nom' => null,
        'reply_to' => null,
        'signature' => '',
        'mentions_legales' => '',
        'contact_rgpd' => null,
    ];

    /** @return array{couleur: string, expediteur_nom: ?string, reply_to: ?string, signature: string, mentions_legales: string, contact_rgpd: ?string} */
    public function getHabillageEmails(): array
    {
        return $this->getSettings()['emails'];
    }

    /** @param array<string, mixed> $habillage */
    public function setHabillageEmails(array $habillage): static
    {
        $settings = $this->getSettings();
        $settings['emails'] = array_merge($settings['emails'], array_intersect_key($habillage, self::HABILLAGE_EMAILS_DEFAUT));

        return $this->setSettings($settings);
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
