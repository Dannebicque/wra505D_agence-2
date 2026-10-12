<?php

namespace StageBundle\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Users\Etudiant;
use Doctrine\ORM\QueryBuilder;
use StageBundle\Entity\Stages\StageEtudiant;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restreint la liste des stages lue par un étudiant aux siens : « Mon stage » appelle
 * `/api/stage_etudiants` sans filtre, et le voter ne juge pas une collection.
 */
final class StudentInternshipsExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $user = $this->security->getUser();
        if (StageEtudiant::class !== $resourceClass || !$user instanceof Etudiant) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('etudiant');
        $queryBuilder
            ->andWhere(sprintf('%s.etudiant = :%s', $rootAlias, $parameter))
            ->setParameter($parameter, $user);
    }
}
