<?php

namespace IntranetBundle\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Utils\LooseValue;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Join;
use IntranetBundle\Enum\EtatJustificatifEnum;
use Symfony\Component\TypeInfo\TypeIdentifier;

class JustificatifAbsenceFilter extends AbstractFilter
{
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ('anneeUniversitaire' === $property) {
            $scolariteSemestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolariteSemestre');
            $scolariteAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolariteSemestreAlias, 'scolarite');
            $anneeUniversitaireAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolariteAlias, 'anneeUniversitaire');
            $param = $queryNameGenerator->generateParameterName('anneeUniversitaire');

            $queryBuilder
                ->andWhere(sprintf('%s.id = :%s', $anneeUniversitaireAlias, $param))
                ->setParameter($param, $value);
        }

        if ('annee' === $property) {
            $scolariteSemestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolariteSemestre');
            $semestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolariteSemestreAlias, 'semestre');
            $anneeAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $semestreAlias, 'annee');
            $param = $queryNameGenerator->generateParameterName('annee');

            $queryBuilder
                ->andWhere(sprintf('%s.id = :%s', $anneeAlias, $param))
                ->setParameter($param, $value);
        }

        if ('etat' === $property) {
            $param = $queryNameGenerator->generateParameterName('etat');

            if (is_string($value) && defined('IntranetBundle\\Enum\\EtatJustificatifEnum::'.$value)) {
                $etat = constant('IntranetBundle\\Enum\\EtatJustificatifEnum::'.$value);
                if (!$etat instanceof EtatJustificatifEnum) {
                    throw new \LogicException(sprintf('%s is not an EtatJustificatifEnum case.', $value));
                }
                $value = $etat->value;
            }

            $queryBuilder
                ->andWhere(sprintf('%s.etat = :%s', $alias, $param))
                ->setParameter($param, $value);
        }

        if ('motif' === $property) {
            $param = $queryNameGenerator->generateParameterName('motif');

            $queryBuilder
                ->andWhere(sprintf('%s.motif LIKE :%s', $alias, $param))
                ->setParameter($param, sprintf('%%%s%%', LooseValue::castString($value)));
        }

        if ('etudiant' === $property) {
            $scolariteSemestreAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $alias, 'scolariteSemestre');
            $scolariteAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolariteSemestreAlias, 'scolarite');
            $etudiantAlias = $this->getOrCreateJoin($queryBuilder, $queryNameGenerator, $scolariteAlias, 'etudiant');
            $param = $queryNameGenerator->generateParameterName('etudiant');

            $queryBuilder
                ->andWhere(sprintf("LOWER(CONCAT(%s.prenom, ' ', %s.nom)) LIKE :%s OR LOWER(CONCAT(%s.nom, ' ', %s.prenom)) LIKE :%s", $etudiantAlias, $etudiantAlias, $param, $etudiantAlias, $etudiantAlias, $param))
                ->setParameter($param, sprintf('%%%s%%', mb_strtolower(LooseValue::castString($value))));
        }

        if ('debut' === $property) {
            $param = $queryNameGenerator->generateParameterName('debut');

            $queryBuilder
                ->andWhere(sprintf('%s.debut >= :%s', $alias, $param))
                ->setParameter($param, $value);
        }

        if ('fin' === $property) {
            $param = $queryNameGenerator->generateParameterName('fin');

            $queryBuilder
                ->andWhere(sprintf('%s.fin <= :%s', $alias, $param))
                ->setParameter($param, $value);
        }
    }

    private function getOrCreateJoin(QueryBuilder $qb, QueryNameGeneratorInterface $queryNameGenerator, string $fromAlias, string $association): string
    {
        foreach (LooseValue::row(LooseValue::row($qb->getDQLPart('join'))[$fromAlias] ?? []) as $join) {
            if ($join instanceof Join && $join->getJoin() === sprintf('%s.%s', $fromAlias, $association)) {
                return $join->getAlias() ?? throw new \LogicException('Doctrine join without alias.');
            }
        }

        $newAlias = $queryNameGenerator->generateJoinAlias($association);
        $qb->leftJoin(sprintf('%s.%s', $fromAlias, $association), $newAlias);

        return $newAlias;
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'annee' => [
                'property' => 'annee',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by annee',
            ],
            'anneeUniversitaire' => [
                'property' => 'anneeUniversitaire',
                'type' => TypeIdentifier::INT->value,
                'required' => false,
                'description' => 'Filter by anneeUniversitaire',
            ],
            'etat' => [
                'property' => 'etat',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter by etat',
            ],
            'motif' => [
                'property' => 'motif',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter by motif',
            ],
            'etudiant' => [
                'property' => 'etudiant',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter by etudiant display',
            ],
            'debut' => [
                'property' => 'debut',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter by start date',
            ],
            'fin' => [
                'property' => 'fin',
                'type' => TypeIdentifier::STRING->value,
                'required' => false,
                'description' => 'Filter by end date',
            ],
        ];
    }
}
