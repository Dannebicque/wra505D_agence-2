<?php

declare(strict_types=1);

namespace App\State\Provider\Timetable;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\Timetable\StudentWeeks;
use App\Entity\Users\Etudiant;
use App\Service\Timetable\StudentTimetableBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Semaines de l'étudiant connecté et leur charge de cours.
 *
 * @implements ProviderInterface<StudentWeeks>
 */
final class StudentWeeksProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly StudentTimetableBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StudentWeeks
    {
        $student = $this->security->getUser();
        if (!$student instanceof Etudiant) {
            throw new AccessDeniedHttpException('Cet emploi du temps est réservé aux étudiants.');
        }

        return new StudentWeeks($this->builder->weeks($student));
    }
}
