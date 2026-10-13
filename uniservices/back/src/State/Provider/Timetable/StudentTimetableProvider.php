<?php

declare(strict_types=1);

namespace App\State\Provider\Timetable;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\Timetable\StudentTimetable;
use App\Entity\Users\Etudiant;
use App\Service\Timetable\StudentTimetableBuilder;
use App\Utils\LooseValue;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Emploi du temps de l'étudiant connecté, pour la semaine demandée. Un personnel a le sien
 * ailleurs.
 *
 * @implements ProviderInterface<StudentTimetable>
 */
final class StudentTimetableProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly StudentTimetableBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StudentTimetable
    {
        $student = $this->security->getUser();
        if (!$student instanceof Etudiant) {
            throw new AccessDeniedHttpException('Cet emploi du temps est réservé aux étudiants.');
        }

        $week = LooseValue::row($context['filters'] ?? [])['semaine'] ?? null;
        if (null === $week) {
            return $this->builder->build($student, new \DateTimeImmutable('today'));
        }

        $day = \is_string($week) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $week) : false;
        if (false === $day || $day->format('Y-m-d') !== $week) {
            throw new BadRequestHttpException('Le paramètre semaine attend une date AAAA-MM-JJ.');
        }

        return $this->builder->build($student, $day);
    }
}
