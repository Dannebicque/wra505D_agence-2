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
