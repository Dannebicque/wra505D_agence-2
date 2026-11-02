<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Parameter;
use App\Utils\LooseValue;
use ApiPlatform\Metadata\Operation;
use App\Entity\Structure\StructureAnnee;
use Doctrine\ORM\QueryBuilder;

class SemestreFilter implements FilterInterface
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

        if ('diplome' === $property) {
            $queryBuilder
                ->innerJoin(StructureAnnee::class, 'sa', 'WITH', sprintf('%s.annee = sa.id', $alias))
                ->innerJoin('sa.pn', 'pn')
                ->innerJoin('pn.anneeUniversitaire', 'au')
                ->innerJoin('pn.diplome', 'd')
                ->andWhere('au.actif = true')
                ->andWhere('d.id = :diplome')
                ->setParameter('diplome', $value)
                ->orderBy(sprintf('%s.ordreLmd', $alias), 'ASC')
                ->addOrderBy(sprintf('%s.libelle', $alias), 'ASC')
            ;
        } elseif ('departement' === $property) {
            $queryBuilder
                ->innerJoin(StructureAnnee::class, 'sa', 'WITH', sprintf('%s.annee = sa.id', $alias))
                ->innerJoin('sa.pn', 'pn')
                ->innerJoin('pn.anneeUniversitaire', 'au')
                ->innerJoin('pn.diplome', 'd')
                ->andWhere('au.actif = true')
                ->andWhere('d.departement = :departement')
                ->setParameter('departement', $value)
                ->orderBy(sprintf('%s.ordreLmd', $alias), 'ASC')
                ->addOrderBy(sprintf('%s.libelle', $alias), 'ASC')
            ;
        } elseif ('annee' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.annee = :annee', $alias))
                ->setParameter('annee', $value)
            ;
        } elseif ('actif' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.actif = :actif', $alias))
                ->setParameter('actif', filter_var($value, FILTER_VALIDATE_BOOLEAN))
            ;
        }
    }
}
