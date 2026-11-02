<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Parameter;
use App\Utils\LooseValue;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class CompetenceFilter implements FilterInterface
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

        // si l'ue passée en paramètre fait partie de la collection des ues de la compétence

        if ('ue' === $property) {
            $ueAlias = $queryNameGenerator->generateJoinAlias('ue');
            $param = $queryNameGenerator->generateParameterName('ue');

            $queryBuilder
                ->innerJoin(sprintf('%s.ues', $alias), $ueAlias)
                ->andWhere(sprintf('%s.id = :%s', $ueAlias, $param))
                ->setParameter($param, $value);

            return;
        }

        if ('referentiel' === $property) {
            $refAlias = $queryNameGenerator->generateJoinAlias('referentiel');
            $param = $queryNameGenerator->generateParameterName('referentiel');

            $queryBuilder
                ->innerJoin(sprintf('%s.referentiel', $alias), $refAlias)
                ->andWhere(sprintf('%s.id = :%s', $refAlias, $param))
                ->setParameter($param, $value)
            ;
        }
    }
}
