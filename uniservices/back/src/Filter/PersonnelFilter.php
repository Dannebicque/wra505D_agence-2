<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Parameter;
use App\Utils\LooseValue;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use App\Entity\Structure\StructureDepartementPersonnel;

class PersonnelFilter implements FilterInterface
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
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ('departement' === $property) {
            $departementPersonnelAlias = $queryNameGenerator->generateJoinAlias('departementPersonnels');
            $departementAlias = $queryNameGenerator->generateJoinAlias('departement');

            $queryBuilder
                ->leftJoin(sprintf('%s.departementsPersonnel', $alias), $departementPersonnelAlias)
                ->leftJoin(sprintf('%s.departement', $departementPersonnelAlias), $departementAlias)
                ->andWhere(sprintf('%s.id = :departement', $departementAlias))
                ->setParameter('departement', $value);
        }
        if ('enseignement' === $property) {
            $enseignementPersonnelAlias = $queryNameGenerator->generateJoinAlias('enseignementPersonnels');
            $enseignementAlias = $queryNameGenerator->generateJoinAlias('enseignement');

            $queryBuilder
                ->leftJoin(sprintf('%s.previsionnels', $alias), $enseignementPersonnelAlias)
                ->leftJoin(sprintf('%s.enseignement', $enseignementPersonnelAlias), $enseignementAlias)
                ->andWhere(sprintf('%s.id = :enseignement', $enseignementAlias))
                ->setParameter('enseignement', $value);
        }
        if (in_array($property, ['enseignant', 'biatss'], true) && filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $role = $property === 'enseignant' ? 'ROLE_TEACHER' : 'ROLE_BIATSS';
            $functionAlias = $queryNameGenerator->generateJoinAlias('fonctionPersonnel');
            $roleParameter = $queryNameGenerator->generateParameterName('fonctionRole');
            // Quoted JSON token matching avoids treating a statutory BIATSS as a non-teacher.
            $scopeQuery = sprintf(
                'SELECT %1$s.id FROM %2$s %1$s WHERE %1$s.personnel = %3$s AND %1$s.permissions LIKE :%4$s',
                $functionAlias,
                StructureDepartementPersonnel::class,
                $alias,
                $roleParameter
            );
            $department = LooseValue::row($context['filters'] ?? [])['departement']
                ?? $operation?->getParameters()?->get('departement')?->getValue();
            if (is_numeric($department)) {
                $departmentParameter = $queryNameGenerator->generateParameterName('fonctionDepartement');
                $scopeQuery .= sprintf(' AND IDENTITY(%s.departement) = :%s', $functionAlias, $departmentParameter);
                $queryBuilder->setParameter($departmentParameter, (int) $department);
            }
            $queryBuilder->andWhere('EXISTS ('.$scopeQuery.')')->setParameter($roleParameter, '%"'.$role.'"%');
        }

        if ('nom' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.nom LIKE :nom', $alias))
                ->setParameter('nom', LooseValue::castString($value).'%');
        }

        if ('prenom' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.prenom LIKE :prenom', $alias))
                ->setParameter('prenom', LooseValue::castString($value).'%');
        }

        if ('mailUniv' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.mailUniv LIKE :mailUniv', $alias))
                ->setParameter('mailUniv', LooseValue::castString($value).'%');
        }

        if ('numeroHarpege' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.numeroHarpege LIKE :numeroHarpege', $alias))
                ->setParameter('numeroHarpege', LooseValue::castString($value).'%');
        }

        if ('username' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.username LIKE :username', $alias))
                ->setParameter('username', LooseValue::castString($value).'%');
        }

        if ('statut' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.statut LIKE :statut', $alias))
                ->setParameter('statut', LooseValue::castString($value).'%');
        }

    }

}
