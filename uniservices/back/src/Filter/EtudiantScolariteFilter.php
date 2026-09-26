<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Operation;
use App\Utils\LooseValue;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\TypeInfo\TypeIdentifier;

#[ApiFilter(EtudiantScolariteFilter::class)]
class EtudiantScolariteFilter extends AbstractFilter
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
            $queryBuilder
                ->join("$alias.departement", 'departement')
                ->andWhere('departement.id = :departement')
                ->setParameter("departement", $value);
        }

        if ('anneeUniversitaire' === $property) {
            $queryBuilder
                ->join("$alias.anneeUniversitaire", 'anneeUniversitaire')
                ->andWhere('anneeUniversitaire.id = :anneeUniversitaire')
                ->setParameter("anneeUniversitaire", $value);
        }

        if ('nom' === $property) {
            $queryBuilder
                ->join("$alias.etudiant", 'etudiant')
                ->andWhere('etudiant.nom LIKE :nom')
                ->setParameter("nom", LooseValue::castString($value).'%');
        }

        if ('prenom' === $property) {
            $queryBuilder
                ->join("$alias.etudiant", 'etudiant')
                ->andWhere('etudiant.prenom LIKE :prenom')
                ->setParameter("prenom", LooseValue::castString($value).'%');
        }

        if ('mailUniv' === $property) {
            $queryBuilder
                ->join("$alias.etudiant", 'etudiant')
                ->andWhere('etudiant.mailUniv LIKE :mailUniv')
                ->setParameter("mailUniv", LooseValue::castString($value).'%');
        }

        if ('annee' === $property) {
            $queryBuilder
                ->join("$alias.scolariteSemestre", 'scolariteSemestre')
                ->join('scolariteSemestre.semestre', 'semestre')
                ->join('semestre.annee', 'annee')
                ->andWhere('annee.id = :annee')
                ->setParameter("annee", $value);
        }



        if ('etudiant' === $property) {
            $queryBuilder
                ->join("$alias.etudiant", 'etudiant')
                ->andWhere('etudiant = :etudiant')
                ->setParameter("etudiant", $value);
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
            'etudiant' => [
                'property' => 'etudiant',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by etudiant',
            ],
        ];
    }
}
