<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\State\ParameterNotFound;
use App\Utils\LooseValue;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class EnseignementFilter implements FilterInterface
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
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder->distinct();

        if ('semestre' === $property) {
            $enseignementUeAlias = $queryNameGenerator->generateJoinAlias('enseignementUe');
            $ueAlias = $queryNameGenerator->generateJoinAlias('ue');

            $queryBuilder
                ->join("$alias.enseignementUes", $enseignementUeAlias)
                ->join("$enseignementUeAlias.ue", $ueAlias)
                ->andWhere("$ueAlias.semestre = :semestre")
                ->setParameter("semestre", $value);
        }

        if ('personnel' === $property) {
            $previsionnelAlias = $queryNameGenerator->generateJoinAlias('previsionnel');
            $personnelAlias = $queryNameGenerator->generateJoinAlias('personnel');
            $anneeUniversitaireAlias = $queryNameGenerator->generateJoinAlias('anneeUniversitaire');

            $queryBuilder
                // si il y a des previsionnels avec des personnels
                ->join("$alias.previsionnels", $previsionnelAlias)
                ->join("$previsionnelAlias.personnel", $personnelAlias)
                ->andWhere("$personnelAlias.id = :personnel")
                ->setParameter("personnel", $value);
            $anneeUniversitaireId = $this->otherFilterValue('anneeUniversitaire', $operation, $context);
            if ((bool) $anneeUniversitaireId) {
                $queryBuilder
                    ->join("$previsionnelAlias.anneeUniversitaire", $anneeUniversitaireAlias)
                    ->andWhere("$anneeUniversitaireAlias.id = :anneeUniversitaireId")
                    ->setParameter("anneeUniversitaireId", $anneeUniversitaireId);
            } else {
                $queryBuilder
                    ->join("$previsionnelAlias.anneeUniversitaire", $anneeUniversitaireAlias)
                    ->andWhere("$anneeUniversitaireAlias.actif = true");
            }
        }

        if ('annee' === $property) {
            $enseignementUeAlias = $queryNameGenerator->generateJoinAlias('enseignementUe');
            $ueAlias = $queryNameGenerator->generateJoinAlias('ue');
            $semestreAlias = $queryNameGenerator->generateJoinAlias('semestre');

            $queryBuilder
                ->join("$alias.enseignementUes", $enseignementUeAlias)
                ->join("$enseignementUeAlias.ue", $ueAlias)
                ->join("$ueAlias.semestre", $semestreAlias)
                ->andWhere("$semestreAlias.annee = :annee")
                ->setParameter("annee", $value);
        }

        if ('departement' === $property) {
            $enseignementUeAlias = $queryNameGenerator->generateJoinAlias('enseignementUe');
            $ueAlias = $queryNameGenerator->generateJoinAlias('ue');
            $semestreAlias = $queryNameGenerator->generateJoinAlias('semestre');
            $anneeAlias = $queryNameGenerator->generateJoinAlias('annee');
            $pnAlias = $queryNameGenerator->generateJoinAlias('pn');
            $anneeUniversitaireAlias = $queryNameGenerator->generateJoinAlias('anneeUniversitaire');
            $diplomeAlias = $queryNameGenerator->generateJoinAlias('diplome');
            $departementAlias = $queryNameGenerator->generateJoinAlias('departement');

            $queryBuilder
                ->join("$alias.enseignementUes", $enseignementUeAlias)
                ->join("$enseignementUeAlias.ue", $ueAlias)
                ->join("$ueAlias.semestre", $semestreAlias)
                ->join("$semestreAlias.annee", $anneeAlias)
                ->join("$anneeAlias.pn", $pnAlias)
                ->join("$pnAlias.anneeUniversitaire", $anneeUniversitaireAlias)
                ->join("$pnAlias.diplome", $diplomeAlias)
                ->join("$diplomeAlias.departement", $departementAlias)
                ->andWhere("$departementAlias.id = :departement")
                ->setParameter("departement", $value);

            $anneeUniversitaireId = $this->otherFilterValue('anneeUniversitaire', $operation, $context);
            if ((bool) $anneeUniversitaireId) {
                $diplomeAnneeUnivAlias = $queryNameGenerator->generateJoinAlias('diplomeAnneeUniv');

                $queryBuilder
                    ->join("$diplomeAlias.anneesUniversitaires", $diplomeAnneeUnivAlias)
                    ->andWhere("$diplomeAnneeUnivAlias.id = :anneeUniversitaireId")
                    ->andWhere("$anneeUniversitaireAlias.id = :anneeUniversitaireId")
                    ->setParameter("anneeUniversitaireId", $anneeUniversitaireId);
            } else {
                $queryBuilder
                    ->andWhere("$anneeUniversitaireAlias.actif = true");
            }
        }


        if ('ue' === $property) {
            $enseignementUeAlias = $queryNameGenerator->generateJoinAlias('enseignementUe');
            $ueAlias = $queryNameGenerator->generateJoinAlias('ue');

            $queryBuilder
                ->join("$alias.enseignementUes", $enseignementUeAlias)
                ->join("$enseignementUeAlias.ue", $ueAlias)
                ->andWhere("$ueAlias.id = :ue")
                ->setParameter("ue", $value);
        }
    }

    /**
     * Valeur d'un autre filtre de la requête. En mode QueryParameter, le contexte ne porte que
     * le paramètre en cours : les autres se lisent sur l'opération.
     *
     * @param array<string, mixed> $context
     */
    private function otherFilterValue(string $name, ?Operation $operation, array $context): mixed
    {
        $value = LooseValue::row($context['filters'] ?? [])[$name]
            ?? $operation?->getParameters()?->get($name)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
    }
}
