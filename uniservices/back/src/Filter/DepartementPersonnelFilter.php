<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\TypeInfo\TypeIdentifier;

#[ApiFilter(DepartementPersonnelFilter::class)]
class DepartementPersonnelFilter extends AbstractFilter
{
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

    public function getDescription(string $resourceClass): array
    {
        return [
            'departement' => [
                'property' => 'departement',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by departement',
            ],
            'personnel' => [
                'property' => 'departement',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by personnel',
            ],
        ];
    }
}
