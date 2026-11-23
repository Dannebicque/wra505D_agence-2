<?php

declare(strict_types=1);

namespace App\State\Provider\Subject;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\Subject\SubjectSheet;
use App\Entity\Users\Etudiant;
use App\Service\Subject\SubjectSheetBuilder;
use App\Utils\LooseValue;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Construit la fiche d'une matière de l'étudiant connecté. Un personnel n'en a pas.
 *
 * @implements ProviderInterface<SubjectSheet>
 */
final class SubjectSheetProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly SubjectSheetBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SubjectSheet
    {
        $student = $this->security->getUser();
        if (!$student instanceof Etudiant) {
            throw new AccessDeniedHttpException('La fiche matière est réservée aux étudiants.');
        }

        return $this->builder->build($student, LooseValue::int($uriVariables['id'] ?? null))
            ?? throw new NotFoundHttpException('Cette matière ne fait pas partie de votre année en cours.');
    }
}
