<?php

declare(strict_types=1);

namespace App\State\Provider\CourseLog;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\CourseLog\CourseLog;
use App\Entity\Users\Etudiant;
use App\Service\CourseLog\CourseLogBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Construit le cahier de texte de l'étudiant connecté. Un personnel n'en a pas.
 *
 * @implements ProviderInterface<CourseLog>
 */
final class CourseLogProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CourseLogBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CourseLog
    {
        $student = $this->security->getUser();
        if (!$student instanceof Etudiant) {
            throw new AccessDeniedHttpException('Le cahier de texte est réservé aux étudiants.');
        }

        $courseLog = $this->builder->build($student);

        return new CourseLog($courseLog['anneeUniversitaire'], $courseLog['echeances'], $courseLog['matieres']);
    }
}
