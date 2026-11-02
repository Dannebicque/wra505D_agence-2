<?php

declare(strict_types=1);

namespace FinanceBundle\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Users\Personnel;
use Doctrine\ORM\QueryBuilder;
use FinanceBundle\Entity\FinanceBonCommande;
use FinanceBundle\Entity\FinanceReception;
use FinanceBundle\Security\FinanceAccess;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restreint les listes de demandes d'achat et de réceptions aux départements où l'utilisateur
 * détient le rôle de consultation : le voter ne juge pas une collection.
 */
final readonly class FinanceDepartmentExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private Security $security,
        private FinanceAccess $access,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (FinanceBonCommande::class !== $resourceClass && FinanceReception::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof Personnel && $this->access->isSuperAdmin($user)) {
            return;
        }

        $departements = $user instanceof Personnel ? $this->access->departementsWith($user, FinanceAccess::ROLE_VIEW) : [];
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $departement = sprintf('%s.departement', $rootAlias);
        if (FinanceReception::class === $resourceClass) {
            $commande = $queryNameGenerator->generateJoinAlias('bonCommande');
            $queryBuilder->join(sprintf('%s.bonCommande', $rootAlias), $commande);
            $departement = sprintf('%s.departement', $commande);
        }

        if ($departements === []) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('departements');
        $queryBuilder
            ->andWhere(sprintf('%s IN (:%s)', $departement, $parameter))
            ->setParameter($parameter, $departements);
    }
}
