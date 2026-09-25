<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Operation;
use App\Entity\Structure\StructureAnnee;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\TypeInfo\TypeIdentifier;

#[ApiFilter(EdtFilter::class)]
class EdtFilter extends AbstractFilter
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

        if ('departement' === $property) {
            $departementAlias = $queryNameGenerator->generateJoinAlias('departement');
            $queryBuilder
                ->leftJoin(sprintf('%s.semestre', $alias), 'semestre')
                ->leftJoin('semestre.annee', 'annee')
                ->leftJoin('annee.pn', 'pn')
                ->leftJoin('pn.diplome', 'diplome')
                ->leftJoin('diplome.departement', $departementAlias)
                ->andWhere(sprintf('%s.id IS NOT NULL', $departementAlias))
                ->andWhere(sprintf('%s.id = :departement', $departementAlias))
                ->setParameter('departement', $value);
        }

        if ('anneeUniversitaire' === $property) {
            $queryBuilder
                ->leftJoin(sprintf('%s.anneeUniversitaire', $alias), 'anneeUniversitaire')
                ->andWhere('anneeUniversitaire.id = :anneeUniversitaire')
                ->setParameter('anneeUniversitaire', $value);
        }

        if ('semaineFormation' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.semaineFormation = :semaineFormation', $alias))
                ->setParameter('semaineFormation', $value);
        }

        if ('personnel' === $property) {
            $queryBuilder
                ->join(sprintf('%s.personnel', $alias), 'personnel')
                ->andWhere('personnel.id = :personnel')
                ->setParameter('personnel', $value);
        }

        if ('semestre' === $property) {
            $queryBuilder
                ->andWhere('semestre.id = :semestre')
                ->setParameter('semestre', $value);
        }

        if ('groupe' === $property) {
            $queryBuilder
                ->join(sprintf('%s.groupe', $alias), 'groupe')
                ->andWhere('groupe.id IN (:groupe)')
                ->setParameter('groupe', (array) $value);
        }

        if ('day' === $property) {
            $date = new \DateTime($value);
            $date->setTime(0, 0, 0);
            $nextDay = clone $date;
            $nextDay->modify('+1 day');

            $queryBuilder
                ->andWhere(sprintf('%s.debut >= :day_start', $alias))
                ->andWhere(sprintf('%s.debut < :day_end', $alias))
                ->setParameter('day_start', $date)
                ->setParameter('day_end', $nextDay);
        }

        if ('debut' === $property) {
            $date = new \DateTime($value);
            $date->setTime(0, 0, 0);

            $queryBuilder
                ->andWhere(sprintf('%s.debut >= :debut', $alias))
                ->setParameter('debut', $date);
        }

        if ('fin' === $property) {
            $date = new \DateTime($value);
            $date->setTime(23, 59, 59);

            $queryBuilder
                ->andWhere(sprintf('%s.fin <= :fin', $alias))
                ->setParameter('fin', $date);
        }

        if ('enseignement' === $property) {
            $queryBuilder
                ->join(sprintf('%s.enseignement', $alias), 'enseignement')
                ->andWhere('enseignement.id = :enseignement')
                ->setParameter('enseignement', $value);
        }

        if ('salle' === $property) {
            $queryBuilder
                ->andWhere(sprintf('%s.salle LIKE :salle', $alias))
                ->setParameter('salle', '%'.$value.'%');
        }

        if ('annee' === $property) {
            $queryBuilder
                ->join(sprintf('%s.semestre', $alias), 'semestre2')
                ->join('semestre2.annee', 'annee2')
                ->andWhere('annee2.id = :annee2')
                ->setParameter('annee2', $value)
            ;
        }

    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'anneeUniversitaire' => [
                'property' => 'anneeUniversitaire',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by année universitaire',
            ],
            'semaineFormation' => [
                'property' => 'semaineFormation',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by semaine de formation',
            ],
            'personnel' => [
                'property' => 'personnel',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by personnel',
            ],
            'departement' => [
                'property' => 'departement',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by department',
            ],
            'semestre' => [
                'property' => 'semestre',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by semester',
            ],
            'day' => [
                'property' => 'day',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter events to only include events on the specified date (format: YYYY-MM-DD)',
            ],
            'debut' => [
                'property' => 'debut',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter events to only include events starting from the specified date (format: YYYY-MM-DD)',
            ],
            'fin' => [
                'property' => 'fin',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter events to only include events ending before the specified date (format: YYYY-MM-DD)',
            ],
            'enseignement' => [
                'property' => 'enseignement',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by enseignement',
            ],
            'salle' => [
                'property' => 'salle',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter by salle (partial match)',
            ],
            'annee' => [
                'property' => 'annee',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by annee',
            ]
        ];
    }
}
