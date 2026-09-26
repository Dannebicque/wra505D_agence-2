<?php

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Structure\StructureCalendrier;
use App\Utils\LooseValue;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProviderInterface<StructureCalendrier> */
class SingleRecordDataProvider implements ProviderInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?StructureCalendrier
    {
        $repository = $this->entityManager->getRepository(StructureCalendrier::class);
        $criteria = [];
        foreach (LooseValue::row($context['filters'] ?? []) as $field => $value) {
            $criteria[(string) $field] = $value;
        }

        return $repository->findOneBy($criteria);
    }
}
