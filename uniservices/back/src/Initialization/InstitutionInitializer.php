<?php

declare(strict_types=1);

namespace App\Initialization;

use App\Entity\Etablissement;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationResult;
use App\Repository\EtablissementRepository;
use App\Utils\LooseValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Crée ou met à jour l'établissement principal à partir de la configuration (`app.establishment`),
 * avant tout import V3 : c'est une donnée de la V4, pas de l'intranet V3.
 */
final readonly class InstitutionInitializer implements InitializerInterface
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EtablissementRepository $institutions,
        #[Autowire(param: 'app.establishment')]
        private array $configuration,
    ) {
    }

    public function getName(): string
    {
        return 'etablissement';
    }

    public function initialize(MigrationContext $context): MigrationResult
    {
        $label = $this->nullableString('libelle');
        if ($label === null) {
            throw new \LogicException('L\'initialisation de l\'établissement exige un libellé (ETABLISSEMENT_LIBELLE).');
        }

        $institution = $this->institutions->findOneBy(['isMain' => true])
            ?? $this->institutions->findOneBy(['libelle' => $label]);
        $isNew = $institution === null;
        $institution ??= new Etablissement();

        $address = $this->configuration['adresse'] ?? null;
        $institution
            ->setLibelle($label)
            ->setSiteWeb($this->nullableString('site_web'))
            ->setTelephone($this->nullableString('telephone'))
            ->setAdresse($address === null ? null : LooseValue::assoc($address));
        $institution->setIsMain(LooseValue::bool($this->configuration['is_main'] ?? true));

        // Champs ajoutés après coup : renseignés seulement s'ils sont configurés, pour ne pas effacer une saisie faite dans l'écran.
        $director = $this->nullableString('directeur');
        if ($director !== null) {
            $institution->setDirecteurNom($director);
        }
        $university = LooseValue::assoc($this->configuration['universite'] ?? []);
        $universityEntity = $institution->getUniversite();
        $text = static fn (string $key): string => trim(LooseValue::string($university[$key] ?? ''));
        if ($text('libelle') !== '') {
            $universityEntity->setLibelle($text('libelle'));
        }
        if ($text('telephone') !== '') {
            $universityEntity->setTelephone($text('telephone'));
        }
        if ($text('president') !== '') {
            $universityEntity->setPresident($text('president'));
        }
        $universityAddress = LooseValue::assoc($university['adresse'] ?? []);
        if (trim(LooseValue::string($universityAddress['ville'] ?? '')) !== '' || trim(LooseValue::string($universityAddress['adresse'] ?? '')) !== '') {
            $universityEntity->setAdresse($universityAddress);
        }

        if ($isNew) {
            $this->entityManager->persist($institution);
        }
        $this->entityManager->flush();

        return new MigrationResult(created: $isNew ? 1 : 0, updated: $isNew ? 0 : 1);
    }

    private function nullableString(string $key): ?string
    {
        $value = trim(LooseValue::string($this->configuration[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
