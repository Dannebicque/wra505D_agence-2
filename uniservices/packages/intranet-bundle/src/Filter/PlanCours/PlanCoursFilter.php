<?php

declare(strict_types=1);

namespace IntranetBundle\Filter\PlanCours;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Utils\LooseValue;
use Doctrine\ORM\QueryBuilder;

/**
 * Filtres des plans de cours : année universitaire, enseignement, responsable, état.
 */
class PlanCoursFilter extends AbstractFilter
{
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

        if ('anneeUniversitaire' === $property) {
            $param = $queryNameGenerator->generateParameterName('anneeUniversitaire');
            $queryBuilder
                ->andWhere(sprintf('%s.anneeUniversitaire = :%s', $alias, $param))
                ->setParameter($param, LooseValue::castInt($value));
        } elseif ('enseignement' === $property) {
            $param = $queryNameGenerator->generateParameterName('enseignement');
            $queryBuilder
                ->andWhere(sprintf('%s.enseignement = :%s', $alias, $param))
                ->setParameter($param, LooseValue::castInt($value));
        } elseif ('responsable' === $property) {
            $param = $queryNameGenerator->generateParameterName('responsable');
            $queryBuilder
                ->andWhere(sprintf('%s.responsable = :%s', $alias, $param))
                ->setParameter($param, LooseValue::castInt($value));
        } elseif ('etatPlanCours' === $property) {
            $param = $queryNameGenerator->generateParameterName('etatPlanCours');
            $queryBuilder
                ->andWhere(sprintf('%s.etatPlanCours = :%s', $alias, $param))
                ->setParameter($param, $value);
        }
    }

    /**
     * @return array<string, array{property: string, type: string, required: bool, description: string}>
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            'anneeUniversitaire' => [
                'property' => 'anneeUniversitaire',
                'type' => 'int',
                'required' => false,
                'description' => 'Filtre par année universitaire',
            ],
            'enseignement' => [
                'property' => 'enseignement',
                'type' => 'int',
                'required' => false,
                'description' => 'Filtre par enseignement',
            ],
            'responsable' => [
                'property' => 'responsable',
                'type' => 'int',
                'required' => false,
                'description' => 'Filtre par responsable',
            ],
            'etatPlanCours' => [
                'property' => 'etatPlanCours',
                'type' => 'string',
                'required' => false,
                'description' => 'Filtre par état du plan de cours (NONE, EN_COURS, COMPLET)',
            ],
        ];
    }
}
