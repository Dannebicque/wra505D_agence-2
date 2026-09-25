<?php

declare(strict_types=1);

namespace App\State\Provider\Transcript;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\Transcript\Transcript;
use App\Entity\Users\Etudiant;
use App\Service\Transcript\TranscriptBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Construit le relevé de l'étudiant connecté. Un personnel n'a pas de relevé.
 *
 * @implements ProviderInterface<Transcript>
 */
final class TranscriptProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly TranscriptBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Transcript
    {
        $student = $this->security->getUser();
        if (!$student instanceof Etudiant) {
            throw new AccessDeniedHttpException('Le relevé de scolarité est réservé aux étudiants.');
        }

        $transcript = $this->builder->build($student);

        return new Transcript($transcript['anneeUniversitaire'], $transcript['semestres']);
    }
}
