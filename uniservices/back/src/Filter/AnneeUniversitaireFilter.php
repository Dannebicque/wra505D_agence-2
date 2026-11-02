<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use App\Utils\LooseValue;
use Doctrine\ORM\QueryBuilder;

/**
 * Années universitaires à partir de (`since`) ou jusqu'à (`until`) une année donnée.
 */
class AnneeUniversitaireFilter implements FilterInterface
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

        // Use the query name generator and reuse existing joins when possible
        if ('since' === $property) {
            // récupérer celle dont annee universitaire est supérieur ou égal à la valeur de since
            $queryBuilder
                ->andWhere(sprintf('%s.annee >= :since', $alias))
                ->setParameter('since', $value);
        }
        if ('until' === $property) {
            // récupérer celle dont annee universitaire est inférieur ou égal à la valeur de until
            $queryBuilder
                ->andWhere(sprintf('%s.annee <= :until', $alias))
                ->setParameter('until', $value);
        }
    }
}
