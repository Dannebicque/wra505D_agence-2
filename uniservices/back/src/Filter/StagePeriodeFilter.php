<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use App\Utils\LooseValue;
use Doctrine\ORM\QueryBuilder;

/**
 * Périodes de stage par département, année universitaire, année active, année du diplôme ou semestre.
 */
class StagePeriodeFilter implements FilterInterface
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
            $this->filterProperty($property, $parameter->getValue(), $queryBuilder, $queryNameGenerator);

            return;
        }

        foreach (LooseValue::row($context['filters'] ?? []) as $property => $value) {
            $this->filterProperty((string) $property, $value, $queryBuilder, $queryNameGenerator);
        }
    }

    private function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator): void
    {
        if (!is_scalar($value) || $value === '') {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parametre = $queryNameGenerator->generateParameterName($property);
        $id = StageEtudiantFilter::entityId($value);

        if ('departement' === $property && $id !== null) {
            $semestre = StageEtudiantFilter::join($queryBuilder, $queryNameGenerator, $alias, 'semestreProgramme');
            $diplome = StageEtudiantFilter::diplomeOf($queryBuilder, $queryNameGenerator, $semestre);
            $queryBuilder->andWhere(sprintf('IDENTITY(%s.departement) = :%s', $diplome, $parametre))->setParameter($parametre, $id);
        }

        if ('anneeUniversitaire' === $property && $id !== null) {
            $anneeUniv = StageEtudiantFilter::join($queryBuilder, $queryNameGenerator, $alias, 'anneeUniversitaire');
            $queryBuilder
                ->andWhere(sprintf('(%1$s.id = :%2$s OR %1$s.annee = :%2$s)', $anneeUniv, $parametre))
                ->setParameter($parametre, $id);
        }

        if ('actif' === $property) {
            $anneeUniv = StageEtudiantFilter::join($queryBuilder, $queryNameGenerator, $alias, 'anneeUniversitaire');
            $queryBuilder
                ->andWhere(sprintf('%s.actif = :%s', $anneeUniv, $parametre))
                ->setParameter($parametre, filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }

        if (in_array($property, ['annee', 'ordre', 'ordreAnnee'], true) && is_numeric($value)) {
            $semestre = StageEtudiantFilter::join($queryBuilder, $queryNameGenerator, $alias, 'semestreProgramme');
            $annee = StageEtudiantFilter::join($queryBuilder, $queryNameGenerator, $semestre, 'annee');
            $queryBuilder->andWhere(sprintf('%s.ordre = :%s', $annee, $parametre))->setParameter($parametre, (int) $value);
        }

        if ('semestre' === $property && $id !== null) {
            $queryBuilder->andWhere(sprintf('IDENTITY(%s.semestreProgramme) = :%s', $alias, $parametre))->setParameter($parametre, $id);
        }
    }
}
