<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Parameter;
use App\Utils\LooseValue;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

class EtudiantFilter implements FilterInterface
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
            $scolaritesAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolarites');
            $deptAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolaritesAlias, 'departement');
            $queryBuilder
                ->andWhere(sprintf('%s.id = :departement', $deptAlias))
                ->setParameter('departement', $value);
        }

        if ('anneeUniversitaire' === $property) {
            $scolaritesAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolarites');
            $anneeUnivAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolaritesAlias, 'anneeUniversitaire');
            $queryBuilder
                ->andWhere(sprintf('%s.id = :anneeUniversitaire', $anneeUnivAlias))
                ->setParameter('anneeUniversitaire', $value);

        }

        if ('semestre' === $property) {
            $scolaritesAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolarites');
            $scolariteSemestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolaritesAlias, 'scolariteSemestre');
            $semestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolariteSemestreAlias, 'semestre');
            $queryBuilder
                ->andWhere(sprintf('%s.id = :semestre', $semestreAlias))
                ->setParameter('semestre', $value);
        }

        if ('annee' === $property) {
            $scolaritesAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolarites');
            // L'année de formation se lit sur le semestre suivi : la scolarité n'en porte pas.
            $scolariteSemestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolaritesAlias, 'scolariteSemestre');
            $semestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolariteSemestreAlias, 'semestre');
            $queryBuilder
                ->andWhere(sprintf('IDENTITY(%s.annee) = :annee', $semestreAlias))
                ->andWhere(sprintf('%s.actif = true', $scolaritesAlias))
                ->setParameter('annee', $value);
        }

        if ('groupe' === $property) {
            $scolaritesAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolarites');
            $scolariteSemestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolaritesAlias, 'scolariteSemestre');
            $groupeAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolariteSemestreAlias, 'groupes');
            $queryBuilder
                ->andWhere(sprintf('%s.id = :groupe', $groupeAlias))
                ->setParameter('groupe', $value);
        }

        if ('sansScolarite' === $property && filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $queryBuilder
                ->leftJoin(sprintf('%s.scolarites', $alias), 'scolaritesSans')
                ->andWhere('scolaritesSans.id IS NULL');
        }
        if ('actif' === $property) {
            $scolaritesAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolarites');
            $queryBuilder
                ->andWhere(sprintf('%s.actif = :actif', $scolaritesAlias))
                ->setParameter('actif', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }

        if ('anneeSortie' === $property || 'annee_sortie' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.annee_sortie = :anneeSortie', $alias))
                ->setParameter('anneeSortie', LooseValue::castInt($value));
        }
    }

    private function getOrCreateJoin(QueryBuilder $qb, QueryNameGeneratorInterface $queryNameGenerator, string $fromAlias, string $association): string
    {
        // Try to find an existing join for the association
        foreach (LooseValue::row($qb->getDQLPart('join')) as $alias => $joins) {
            foreach (LooseValue::row($joins) as $join) {
                if ($join instanceof Join && $join->getJoin() === "$fromAlias.$association") {
                    return $join->getAlias() ?? throw new \LogicException('Doctrine join without alias.');
                }
            }
        }

        // If not found, generate a new alias and add the join
        $newAlias = $queryNameGenerator->generateJoinAlias($association);
        $qb->join("$fromAlias.$association", $newAlias);
        return $newAlias;
    }

}
