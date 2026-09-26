<?php

namespace HelpdeskBundle\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Operation;
use App\Entity\Structure\StructureAnnee;
use App\Utils\LooseValue;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\TypeInfo\TypeIdentifier;

#[ApiFilter(TicketFilter::class)]
class TicketFilter extends AbstractFilter
{
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ('latest' === $property) {
            $queryBuilder
                ->orderBy(sprintf('%s.created', $alias), 'DESC')
                ->setMaxResults(LooseValue::nullableInt($value));
            ;
        }

        if ('auteur' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.auteur = :%s', $alias, $property))
                ->setParameter($property, $value);
        }

        if ('statut' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.statut = :%s', $alias, $property))
                ->setParameter($property, $value);
        }
        if ('hasRecentMessage' === $property && $value) {
            $dateLimite = new \DateTimeImmutable('-7 days');

            $queryBuilder
                ->leftJoin(sprintf('%s.helpdeskMessages', $alias), 'm')
                ->andWhere('m.created >= :dateLimite')
                ->setParameter('dateLimite', $dateLimite);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'latest' => [
                'property' => 'latest',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by latests tickets',
            ],
            'auteur' => [
                'property' => 'auteur',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by auteur',
            ],
            'statut' => [
                'property' => 'statut',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter by statut',
            ],
            'hasRecentMessage' => [
                'property' => 'hasRecentMessage',
                'type' => TypeIdentifier::BOOL->value,
                'required' => false,
                'description' => 'Filter by new message',
            ]
        ];

    }
}
