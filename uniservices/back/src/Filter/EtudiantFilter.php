<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Operation;
use App\Utils\LooseValue;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\TypeInfo\TypeIdentifier;

#[ApiFilter(EtudiantFilter::class)]
class EtudiantFilter extends AbstractFilter
{
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ('departement' === $property) {
            $queryBuilder
                ->join(sprintf('%s.scolarites', $alias), 'scolarites')
                ->join('scolarites.departement', 'departement')
                ->andWhere('departement.id = :departement')
                ->setParameter('departement', $value);
        }

        if ('anneeUniversitaire' === $property) {
            $queryBuilder
                ->join(sprintf('%s.scolarites', $alias), 'scolarites')
                ->join('scolarites.anneeUniversitaire', 'anneeUniversitaire')
                ->andWhere('anneeUniversitaire.id = :anneeUniversitaire')
                ->setParameter('anneeUniversitaire', $value);

            $annee = LooseValue::row($context['filters'] ?? [])['annee'] ?? null;
            if (null !== $annee) {
                $queryBuilder
                    ->join('scolarites.annee', 'annee')
                    ->andWhere('annee.id = :annee')
                    ->setParameter('annee', $annee);
            }
        }

        if ('semestre' === $property) {
            $queryBuilder
                ->join(sprintf('%s.scolarites', $alias), 'scolarites')
                ->join('scolarites.semestre', 'scolariteSemestre')
                ->join('scolariteSemestre.semestre', 'semestre')
                ->andWhere('semestre.id = :semestre')
                ->setParameter('semestre', $value);
        }

        if ('annee' === $property) {
            $queryBuilder
                ->join(sprintf('%s.scolarites', $alias), 'scolarites')
                ->join('scolarites.annee', 'annee')
                ->andWhere('annee.id = :annee')
                ->andWhere('scolarites.actif = true')
                ->setParameter('annee', $value);
        }

        if ('groupe' === $property) {
            $queryBuilder
                ->join(sprintf('%s.scolarites', $alias), 'scolarites')
                ->join('scolarites.scolariteSemestre', 'scolariteSemestre')
                ->join('scolariteSemestre.groupes', 'groupe')
                ->andWhere('groupe.id = :groupe')
                ->setParameter('groupe', $value);
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
            'anneeUniversitaire' => [
                'property' => 'anneeUniversitaire',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by anneeUniversitaire',
            ],
            'semestre' => [
                'property' => 'semestre',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by semestre',
            ],
            'annee' => [
                'property' => 'annee',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by annee',
            ],
            'groupe' => [
                'property' => 'groupe',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by groupe',
            ],
        ];
    }
}
