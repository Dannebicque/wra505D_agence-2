<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Parameter;
use App\Utils\LooseValue;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class DepartementPersonnelFilter implements FilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;

    /**
     * Appelé une fois par paramètre déclaré sur la ressource (QueryParameter), ou une fois pour
     * tous les filtres de la requête quand le filtre est déclaré par #[ApiFilter].
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'] ?? null;
        if ($parameter instanceof Parameter) {
            $property = $parameter->getProperty() ?? $parameter->getKey() ?? '';
            $this->filterProperty($property, $parameter->getValue(), $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);

            return;
        }

        foreach (LooseValue::row($context['filters'] ?? []) as $property => $value) {
            $this->filterProperty((string) $property, $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ('departement' === $property) {
            $departementAlias = $queryNameGenerator->generateJoinAlias('departement');
            $queryBuilder
                ->join("$alias.departement", $departementAlias)
                ->andWhere("$departementAlias.id = :departementId")
                ->setParameter('departementId', $value);
        }
        if ('personnel' === $property) {
            $personnelAlias = $queryNameGenerator->generateJoinAlias('personnel');
            $queryBuilder
                ->join("$alias.personnel", $personnelAlias)
                ->andWhere("$personnelAlias.id = :personnelId")
                ->setParameter('personnelId', $value);
        }
        if ('structureDepartementPersonnelId' === $property) {
            $queryBuilder
                ->where("$alias.id = :structureDepartementPersonnelId")
                ->setParameter('structureDepartementPersonnelId', $value);
        }
    }

}
