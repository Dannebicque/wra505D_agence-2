<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use App\Utils\LooseValue;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Stages d'étudiants par période, tuteur universitaire, étudiant, année universitaire ou département.
 */
class StageEtudiantFilter implements FilterInterface
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
        $id = self::entityId($value);
        if ($id === null) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parametre = $queryNameGenerator->generateParameterName($property);

        if ('tuteurUniversitaire' === $property) {
            $queryBuilder->andWhere(sprintf('IDENTITY(%s.tuteurUniversitaire) = :%s', $alias, $parametre))->setParameter($parametre, $id);
        }

        if ('stagePeriode' === $property) {
            $queryBuilder->andWhere(sprintf('IDENTITY(%s.stagePeriode) = :%s', $alias, $parametre))->setParameter($parametre, $id);
        }

        if ('etudiant' === $property) {
            $queryBuilder->andWhere(sprintf('IDENTITY(%s.etudiant) = :%s', $alias, $parametre))->setParameter($parametre, $id);
        }

        if ('anneeUniversitaire' === $property) {
            $periode = self::join($queryBuilder, $queryNameGenerator, $alias, 'stagePeriode');
            $anneeUniv = self::join($queryBuilder, $queryNameGenerator, $periode, 'anneeUniversitaire');
            $queryBuilder
                ->andWhere(sprintf('(%1$s.id = :%2$s OR %1$s.annee = :%2$s)', $anneeUniv, $parametre))
                ->setParameter($parametre, $id);
        }

        if ('departement' === $property) {
            $periode = self::join($queryBuilder, $queryNameGenerator, $alias, 'stagePeriode');
            $diplome = self::diplomeOf($queryBuilder, $queryNameGenerator, self::join($queryBuilder, $queryNameGenerator, $periode, 'semestreProgramme'));
            $queryBuilder->andWhere(sprintf('IDENTITY(%s.departement) = :%s', $diplome, $parametre))->setParameter($parametre, $id);
        }
    }

    /**
     * Identifiant passé en nombre ou en IRI (`/api/…/12`).
     */
    public static function entityId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        $last = basename($value);

        return ctype_digit($last) && (int) $last > 0 ? (int) $last : null;
    }

    /**
     * Diplôme d'un semestre, par son année et son programme national.
     */
    public static function diplomeOf(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $semestre): string
    {
        $annee = self::join($queryBuilder, $queryNameGenerator, $semestre, 'annee');
        $pn = self::join($queryBuilder, $queryNameGenerator, $annee, 'pn');

        return self::join($queryBuilder, $queryNameGenerator, $pn, 'diplome');
    }

    /**
     * Réutilise la jointure si un autre paramètre l'a déjà posée.
     */
    public static function join(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $from, string $association): string
    {
        $parts = $queryBuilder->getDQLPart('join');
        foreach (is_array($parts) ? $parts : [] as $joins) {
            foreach (is_array($joins) ? $joins : [] as $join) {
                if ($join instanceof Join && $join->getJoin() === $from . '.' . $association && $join->getAlias() !== null) {
                    return $join->getAlias();
                }
            }
        }

        $alias = $queryNameGenerator->generateJoinAlias($association);
        $queryBuilder->leftJoin($from . '.' . $association, $alias);

        return $alias;
    }
}
